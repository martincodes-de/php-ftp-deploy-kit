<?php
declare(strict_types=1);

namespace Pfdk\Tests\Support;

/** Stands in for the curl call: a tiny FTP server with files in memory that can lose or refuse single commands. */
final class FakeFtpServer
{
    /** @var array<string,string> */
    public array $files = [];

    /** @var list<string> */
    public array $log = [];

    public bool $refuseRenameOverExisting = false;
    public bool $reportNoSize = false;
    public ?int $keepOnlyBytes = null;

    /** @var list<array{op:string,errno:int,afterEffect:bool,times:int,path:?string,skip:int}> */
    private array $failures = [];

    /** @param string $op PUT, SIZE, RENAME or DELE; with $afterEffect the command is carried out but its answer is lost; $skip lets that many matching commands succeed first */
    public function fail(string $op, int $errno, bool $afterEffect = false, int $times = 1, ?string $path = null, int $skip = 0): void
    {
        $this->failures[] = compact('op', 'errno', 'afterEffect', 'times', 'path', 'skip');
    }

    /** @return list<string> */
    public function commands(string $op): array
    {
        return array_values(array_filter($this->log, static fn (string $line): bool => str_starts_with($line, "$op ")));
    }

    /**
     * @param array<int,mixed> $options
     *
     * @return array{ok:bool,errno:int,error:string,size:int}
     */
    public function __invoke(string $url, array $options): array
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if (isset($options[CURLOPT_QUOTE])) {
            [$op, $path, $to] = $this->parseQuote($options[CURLOPT_QUOTE]);
            $line = $op === 'RENAME' ? "RENAME $path -> $to" : "DELE $path";
        } else {
            $op = isset($options[CURLOPT_UPLOAD]) ? 'PUT' : 'SIZE';
            $line = "$op $path";
        }
        $this->log[] = $line;

        $failure = $this->matchingFailure($op, $path);
        if ($failure !== null && !$this->failures[$failure]['afterEffect']) {
            return $this->consume($failure);
        }

        $result = $this->carryOut($op, $path, $to ?? '', $options);
        if ($failure !== null && $result['ok']) {
            return $this->consume($failure);
        }

        return $result;
    }

    /**
     * @param list<string> $commands
     *
     * @return array{0:string,1:string,2:string}
     */
    private function parseQuote(array $commands): array
    {
        if (str_starts_with($commands[0], 'DELE ')) {
            return ['DELE', substr($commands[0], 5), ''];
        }

        return ['RENAME', substr($commands[0], 5), substr($commands[1], 5)];
    }

    private function matchingFailure(string $op, string $path): ?int
    {
        foreach ($this->failures as $index => $failure) {
            if ($failure['op'] === $op && $failure['times'] > 0 && ($failure['path'] === null || str_ends_with($path, $failure['path']))) {
                if ($failure['skip'] > 0) {
                    --$this->failures[$index]['skip'];

                    continue;
                }

                return $index;
            }
        }

        return null;
    }

    /** @return array{ok:bool,errno:int,error:string,size:int} */
    private function consume(int $index): array
    {
        --$this->failures[$index]['times'];
        $errno = $this->failures[$index]['errno'];

        return ['ok' => false, 'errno' => $errno, 'error' => "simulated error $errno", 'size' => -1];
    }

    /**
     * @param array<int,mixed> $options
     *
     * @return array{ok:bool,errno:int,error:string,size:int}
     */
    private function carryOut(string $op, string $path, string $to, array $options): array
    {
        $ok = ['ok' => true, 'errno' => 0, 'error' => '', 'size' => -1];
        $error = static fn (int $errno, string $message): array => ['ok' => false, 'errno' => $errno, 'error' => $message, 'size' => -1];

        switch ($op) {
            case 'PUT':
                $contents = (string) stream_get_contents($options[CURLOPT_INFILE]);
                $this->files[$path] = $this->keepOnlyBytes === null ? $contents : substr($contents, 0, $this->keepOnlyBytes);

                return $ok;
            case 'SIZE':
                if (!isset($this->files[$path])) {
                    return $error(78, 'not found');
                }

                return ['size' => $this->reportNoSize ? -1 : strlen($this->files[$path])] + $ok;
            case 'RENAME':
                if (!isset($this->files[$path])) {
                    return $error(21, 'RNFR failed');
                }
                if ($this->refuseRenameOverExisting && isset($this->files[$to])) {
                    return $error(21, 'RNTO refused: file exists');
                }
                $this->files[$to] = $this->files[$path];
                unset($this->files[$path]);

                return $ok;
            default:
                if (!isset($this->files[$path])) {
                    return $error(21, 'no such file');
                }
                unset($this->files[$path]);

                return $ok;
        }
    }
}
