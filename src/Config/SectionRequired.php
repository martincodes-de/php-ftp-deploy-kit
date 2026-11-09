<?php
declare(strict_types=1);

namespace Pfdk\Config;

final class SectionRequired extends \RuntimeException
{
    /** @param list<string> $sections */
    public function __construct(public readonly array $sections)
    {
        parent::__construct('This ini has several targets; pick one with --section: ' . implode(', ', $sections));
    }
}
