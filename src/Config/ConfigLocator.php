<?php
declare(strict_types=1);

namespace Pfdk\Config;

final class ConfigLocator
{
    /**
     * `deploy.ini` in the folder wins; otherwise any `deploy*.ini` in the folder or one level below
     * (not the `.example` templates).
     *
     * @return list<string>
     */
    public static function candidates(string $folder): array
    {
        if (is_file("$folder/deploy.ini")) {
            return ["$folder/deploy.ini"];
        }

        $found = [...(glob("$folder/deploy*.ini") ?: []), ...(glob("$folder/*/deploy*.ini") ?: [])];
        sort($found);

        return $found;
    }
}
