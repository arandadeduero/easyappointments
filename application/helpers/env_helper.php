<?php
/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Environment helper.
 *
 * Loads secrets from an optional ".env" file placed in the root of the installation (next to "index.php") and exposes
 * them through the env() function. The file is ignored by git, see ".env.example" for the supported variables.
 *
 * This file is required directly by "index.php" before the framework boots (and later autoloaded as a regular helper),
 * so it must not depend on CodeIgniter and does not carry the usual BASEPATH guard.
 */

if (!function_exists('parse_env_contents')) {
    /**
     * Parse the contents of a ".env" file.
     *
     * Supported syntax: one "NAME=value" pair per line, blank lines, "#" comment lines and values optionally wrapped in
     * single or double quotes. Anything else is ignored.
     *
     * @param string $contents Raw file contents.
     *
     * @return array<string, string> Parsed variables.
     */
    function parse_env_contents(string $contents): array
    {
        $variables = [];

        foreach (preg_split('/\r\n|\r|\n/', $contents) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);

            $name = trim($name);

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                continue;
            }

            $value = trim($value);

            $length = strlen($value);

            if ($length >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$length - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            }

            $variables[$name] = $value;
        }

        return $variables;
    }
}

if (!function_exists('load_env_file')) {
    /**
     * Load a ".env" file into the process environment.
     *
     * Variables that are already defined in the real environment (e.g. by the web server or the shell) are not
     * overwritten, so they always take precedence over the file.
     *
     * @param string $path Absolute path of the ".env" file.
     *
     * @return array<string, string> Variables that were loaded from the file.
     */
    function load_env_file(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $loaded = [];

        foreach (parse_env_contents($contents) as $name => $value) {
            if (getenv($name) !== false || array_key_exists($name, $_ENV)) {
                continue;
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $loaded[$name] = $value;
        }

        return $loaded;
    }
}

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * Example:
     *
     * $debug = env('debug', FALSE);
     *
     * @param string $name Environment key.
     * @param mixed|null $default Value returned when the variable is not defined or is empty.
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    function env(string $name, mixed $default = null): mixed
    {
        if (empty($name)) {
            throw new InvalidArgumentException('The $key argument cannot be empty.');
        }

        $value = $_ENV[$name] ?? getenv($name);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}
