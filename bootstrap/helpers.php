<?php

namespace Busarm\PhpMini\Helpers;

use Busarm\PhpMini\Async;
use Busarm\PhpMini\Errors\SystemError;
use Busarm\PhpMini\Tasks\Task;
use Closure;
use Generator;
use Laravel\SerializableClosure\SerializableClosure;
use ReflectionObject;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Process\Process;

/**
 * PHP Mini Framework
 *
 * @copyright busarm.com
 * @license https://github.com/Busarm/php-mini/blob/master/LICENSE (MIT License)
 */

########## FEATURE HELPERS ############

/**
 * Convert to proper unit
 * @param int|float $size
 * @return string
 */
function unit_convert($size)
{
    $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
    $index = floor(log($size, 1024));
    return (round($size / pow(1024, ($index)), 2) ?? 0) . ' ' . $unit[$index] ?? '~';
}

/**
 * Parses http query string into an array
 *
 * @author Alxcube <alxcube@gmail.com>
 *
 * @param string $queryString String to parse
 * @param string $argSeparator Query arguments separator
 * @param integer $decType Decoding type
 * @return array
 */
function http_parse_query($queryString, $argSeparator = '&', $decType = PHP_QUERY_RFC1738)
{
    $result             = array();
    $parts              = explode($argSeparator, $queryString);

    foreach ($parts as $part) {
        $partList = explode('=', $part, 2);
        if (count($partList) !== 2) continue;
        list($paramName, $paramValue)   = $partList;

        switch ($decType) {
            case PHP_QUERY_RFC3986:
                $paramName      = rawurldecode($paramName);
                $paramValue     = rawurldecode($paramValue);
                break;

            case PHP_QUERY_RFC1738:
            default:
                $paramName      = urldecode($paramName);
                $paramValue     = urldecode($paramValue);
                break;
        }


        if (preg_match_all('/\[([^\]]*)\]/m', $paramName, $matches)) {
            $paramName      = substr($paramName, 0, strpos($paramName, '['));
            $keys           = array_merge(array($paramName), $matches[1]);
        } else {
            $keys           = array($paramName);
        }

        $target         = &$result;

        foreach ($keys as $index) {
            if ($index === '') {
                if (isset($target)) {
                    if (is_array($target)) {
                        $intKeys        = array_filter(array_keys($target), 'is_int');
                        $index  = count($intKeys) ? max($intKeys) + 1 : 0;
                    } else {
                        $target = array($target);
                        $index  = 1;
                    }
                } else {
                    $target         = array();
                    $index          = 0;
                }
            } elseif (isset($target[$index]) && !is_array($target[$index])) {
                $target[$index] = array($target[$index]);
            }

            $target         = &$target[$index];
        }

        if (is_array($target)) {
            $target[]   = $paramValue;
        } else {
            $target     = $paramValue;
        }
    }

    return $result;
}

/**
 * Get Server Variable
 *
 * @param string $name
 * @param string $default
 * @return string
 */
function env($name, $default = null)
{
    $data = getenv($name) ?? false;
    return $data !== false ? $data : $default;
}

/**
 * Is CLI?
 *
 * Test to see if a request was made from the command line.
 *
 * @return 	bool
 */
function is_cli()
{
    return (PHP_SAPI === 'cli' or defined('STDIN'));
}

/**
 * Print output end exit
 * @param mixed $data
 * @param int $responseCode
 */
function out($data = null, $responseCode = 500)
{
    if (!is_array($data) && !is_object($data)) {
        return is_cli() ? die(PHP_EOL . $data . PHP_EOL) : (new \Busarm\PhpMini\Response())->html($data, $responseCode)->send(false);
    }
    return is_cli() ? die(PHP_EOL . var_export($data, true) . PHP_EOL) : (new \Busarm\PhpMini\Response())->json((array)$data, $responseCode)->send(false);
}


########## APPLICATION HELPERS ############

/**
 * Get current app instance
 * @return \Busarm\PhpMini\App
 */
function app(): \Busarm\PhpMini\App
{
    return \Busarm\PhpMini\App::getInstance() ?? throw new SystemError('Failed to get current app instance');
}

/**
 * 
 * Get or Set config
 *
 * @param string $name
 * @param mixed $value
 * @return mixed
 */
function config($name, $value = null)
{
    try {
        return app()->config->get($name) ?? ($value ? app()->config->set($name, $value) : null);
    } catch (\Throwable $th) {
        return null;
    }
}

/**
 * Load view file
 *
 * @param string $path
 * @param array $params
 * @param boolean $return Print out view or return content
 * @return mixed
 */
function view(string $path, $params = [], $return = false)
{
    return app()->loader->view($path, $params, $return);
}

/**
 * Get app loader object
 * @return \Busarm\PhpMini\Interfaces\LoaderInterface
 */
function &load()
{
    return app()->loader;
}

/**
 * Get app reporter object
 * @return \Busarm\PhpMini\Interfaces\ReportingInterface
 */
function &report()
{
    return app()->reporter;
}

/**
 * Get app router object
 * @return \Busarm\PhpMini\Interfaces\RouterInterface
 */
function &router()
{
    return app()->router;
}

/**
 * @param string $level @see \Psr\Log\LogLevel
 * @param mixed $message
 * @param array $context
 */
function log_message($level, $message, array $context = [])
{
    $message = is_array($message) || is_object($message) ? var_export($message, true) : (string) $message;
    $message = date("Y-m-d H:i:s.", microtime(true)) . substr(gettimeofday()["usec"] ?? '0000', 0, 4) . " - " . $message;
    try {
        app()->logger->log($level, $message, $context);
    } catch (\Throwable $th) {
        (new ConsoleLogger(new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG)))->log($level, $message, $context);
    }
}

/**
 * @param mixed $message
 */
function log_emergency(...$message)
{
    foreach ($message as $log) {
        log_message(\Psr\Log\LogLevel::EMERGENCY, $log);
    }
}

/**
 * @param mixed $message
 */
function log_error(...$message)
{
    foreach ($message as $log) {
        log_message(\Psr\Log\LogLevel::ERROR, $log);
    }
}

/**
 * @param \Exception $exception
 */
function log_exception($exception)
{
    log_message(\Psr\Log\LogLevel::ERROR, $exception->getMessage(), $exception->getTrace());
}

/**
 * @param mixed $message
 */
function log_info(...$message)
{
    foreach ($message as $log) {
        log_message(\Psr\Log\LogLevel::INFO, $log);
    }
}

/**
 * @param mixed $message
 */
function log_debug(...$message)
{
    foreach ($message as $log) {
        log_message(\Psr\Log\LogLevel::DEBUG, $log);
    }
}

/**
 * @param mixed $message
 */
function log_warning(...$message)
{
    foreach ($message as $log) {
        log_message(\Psr\Log\LogLevel::WARNING, $log);
    }
}

/**
 * Run external command
 *
 * @param string $command
 * @param array $params
 * @param \Symfony\Component\Console\Output\OutputInterface $output
 * @param int $timeout Default = 600 seconds
 * @param boolean $wait Default = true
 * @return \Symfony\Component\Process\Process
 */
function run(string $command, array $params, \Symfony\Component\Console\Output\OutputInterface $output, $timeout = 600, $wait = true)
{
    $output->getFormatter()->setStyle('error', new \Symfony\Component\Console\Formatter\OutputFormatterStyle('red'));
    $process = new Process([
        $command,
        ...array_filter($params, fn ($arg) => !empty($arg))
    ]);
    $process->setTimeout($timeout);
    if ($wait) {
        $process->run(function ($type, $data) use ($output) {
            if ($type == Process::ERR) {
                $output->writeln('<error>' . $data . '</error>');
            } else {
                $output->writeln('<comment>' . $data . '</comment>');
            }
        });
    } else {
        $process->start(function ($type, $data) use ($output) {
            if ($type == Process::ERR) {
                $output->writeln('<error>' . $data . '</error>');
            } else {
                $output->writeln('<comment>' . $data . '</comment>');
            }
        });
    }
    return $process;
}

/**
 * Run external command asynchronously
 *
 * @param string $command
 * @param array $params
 * @param \Symfony\Component\Console\Output\OutputInterface $output
 * @param int $timeout Default = 600 seconds
 * @return \Symfony\Component\Process\Process
 */
function run_async(string $command, array $params, \Symfony\Component\Console\Output\OutputInterface $output, $timeout = 600)
{
    return run($command, $params, $output, $timeout, false);
}


########### ARRAY HELPERS #################

/**
 * Check if any item in array validates to `true`
 *
 * @param array $list
 * @return boolean
 */
function any(array $list): bool
{
    return in_array(true, array_map(fn ($item) => !empty($item), $list));
}

/**
 * Check if all items in array validates to `true`
 *
 * @param array $list
 * @return boolean
 */
function all(array $list): bool
{
    return !in_array(false, array_map(fn ($item) => !empty($item), $list));
}

/**
 * Find item in list by checking against predicate function
 *
 * @param callable $fn Predicate function. Return `true` if matched, else `false`
 * @param array $list List to check
 * @return mixed found item or `null` if failed
 */
function find(callable $fn, array $list): mixed
{
    foreach ($list as $item) {
        if ($fn($item) != false)
            return $item;
    }
    return null;
}

/**
 * Check if array is a list - [a,b,c] not [a=>1,b=>2,c=>3]
 *
 * @param array $list
 * @return boolean
 */
function is_list(array $list): bool
{
    return array_values($list) === $list;
}


/**
 * Create 'Set-Cookie' header value
 *
 * @param string $name
 * @param string $value
 * @param integer $expires
 * @param string $path
 * @param string $domain
 * @param string $samesite
 * @param boolean $secure
 * @param boolean $httponly
 * @return string
 */
function create_cookie_header(
    string $name,
    string $value,
    int $expires = 0,
    string $path = "",
    string $domain = "",
    string $samesite = "",
    bool $secure = false,
    bool $httponly = false
): string {
    $value = rawurlencode($value);
    $date = date("D, d-M-Y H:i:s", $expires) . ' GMT';
    $header = "{$name}={$value}";
    if ($expires != 0) {
        $header .= "; Expires={$date}; Max-Age=" . ($expires - time());
    }
    if ($path != "") {
        $header .= "; Path=" . $path;
    }
    if ($domain != "") {
        $header .= "; Domain=" . $domain;
    }
    if ($samesite != "") {
        $header .= "; SameSite=" . $samesite;
    }
    if ($secure) {
        $header .= "; Secure";
    }
    if ($httponly) {
        $header .= "; HttpOnly";
    }
    return $header;
}

/**
 * Find a free port on the system
 *
 * @return int
 */
function find_free_port()
{
    $sock = socket_create_listen(0);
    socket_getsockname($sock, $addr, $port);
    socket_close($sock);

    return $port;
}

/**
 * This function returns the maximum files size that can be uploaded 
 * in PHP
 * @return int size in kilobytes
 **/
function get_max_upload_size($default = 1024)
{
    return min(parse_php_size(ini_get('post_max_size')), parse_php_size(ini_get('upload_max_filesize'))) ?: $default * 1024;
}

/**
 * This function transforms the php.ini notation for numbers (like '2M') to an integer (2*1024*1024 in this case)
 * 
 * @param string $sSize
 * @return integer The value in bytes
 */
function parse_php_size($sSize)
{
    $sSuffix = strtoupper(substr($sSize, -1));
    if (!in_array($sSuffix, array('P', 'T', 'G', 'M', 'K'))) {
        return (int)$sSize;
    }
    $iValue = substr($sSize, 0, -1);
    switch ($sSuffix) {
        case 'P':
            $iValue *= 1024;
            // Fallthrough intended
        case 'T':
            $iValue *= 1024;
            // Fallthrough intended
        case 'G':
            $iValue *= 1024;
            // Fallthrough intended
        case 'M':
            $iValue *= 1024;
            // Fallthrough intended
        case 'K':
            $iValue *= 1024;
            break;
    }
    return (int)$iValue;
}


/**
 * Run task as async  (NON - Blocking)
 * 
 * @param Task|callable $task
 */
function async(Task|callable $task): mixed
{
    return  Async::runTask($task, false);
}

/**
 * Run task as async and wait for result (Blocking)
 * 
 * @param Task|callable $task
 */
function await(Task|callable $task): mixed
{
    return Async::runTask($task, true);
}

/**
 * Run task list concurrently
 * 
 * @param Task[]|callable[] $task
 * @param bool $wait
 */
function concurrent(array $tasks, $wait = false): Generator
{
    return Async::runTasks($tasks, $wait);
}

/**
 * Listen to event
 * 
 * @param string $event
 * @param callable|class-string<Task> $listner
 */
function listen(string $event, callable|string $listner)
{
    app()->eventManager->addEventListner($event, $listner);
}

/**
 * Dispatch event
 * 
 * @param string $event
 * @param array $data
 */
function dispatch(string $event, array $data = [])
{
    app()->eventManager->dispatchEvent($event, $data);
}


/**
 * Wrap data to be serialized
 *
 * @param mixed $data
 * @return string
 */
function wrapSerializable($data)
{
    if ($data instanceof Closure) {
        $data = new SerializableClosure($data);
    } else if (is_callable($data)) {
        $data = new SerializableClosure(Closure::fromCallable($data));
    } else if (is_array($data)) {
        $data = array_map(function ($value) {
            return wrapSerializable($value);
        }, $data);
    } else if (is_object($data)) {
        $reflection = new ReflectionObject($data);
        // do {
        if ($reflection->isUserDefined()) {
            foreach ($reflection->getProperties() as $prop) {
                if (
                    !$prop->isStatic()
                    && !$prop->isReadOnly()
                    && $prop->getDeclaringClass()->isUserDefined()
                    && $prop->isInitialized($data)
                ) {
                    $value = $prop->getValue($data);
                    if (isset($value)) {
                        $prop->setValue($data, wrapSerializable($value));
                    }
                }
            }
        }
        // } while ($reflection = $reflection->getParentClass());
    }
    return $data;
}

/**
 * Unwrap data that was unserialized
 *
 * @param mixed $data
 * @return string
 */
function unwrapSerializable($data)
{
    if ($data instanceof SerializableClosure) {
        $data = $data->getClosure();
    } else if (is_array($data)) {
        $data = array_map(function ($value) {
            return unwrapSerializable($value);
        }, $data);
    } else if (is_object($data)) {
        $reflection = new ReflectionObject($data);
        do {
            if ($reflection->isUserDefined()) {
                foreach ($reflection->getProperties() as $prop) {
                    if (
                        !$prop->isStatic()
                        && !$prop->isReadOnly()
                        && $prop->getDeclaringClass()->isUserDefined()
                        && $prop->isInitialized($data)
                    ) {
                        $value = $prop->getValue($data);
                        if (isset($value)) {
                            $prop->setValue($data, unwrapSerializable($value));
                        }
                    }
                }
            } else break;
        } while ($reflection = $reflection->getParentClass());
    }
    return $data;
}

/**
 * Serialize
 *
 * @param mixed $data
 * @return string
 */
function serialize($data)
{
    return \serialize(wrapSerializable($data));
}

/**
 * Unserialize
 *
 * @param string $data
 * @param array|null $options
 * @return mixed
 */
function unserialize($data, array $options = [])
{
    return unwrapSerializable(\unserialize($data, $options));
}

/**
 * Read from stream socket
 * 
 * @param resource $socket
 * @param int $length
 * @return string
 */
function stream_read(mixed $socket, int $length): string
{
    $response = "";
    while (!feof($socket)) {
        $response .= fread($socket, $length);
        $stream_meta_data = stream_get_meta_data($socket);
        if (($stream_meta_data['unread_bytes'] ?? 0) <= 0) break;
    }
    return $response;
}

/**
 * Write to stream socket
 * 
 * @param resource $socket
 * @param string $data
 * @param int $length
 */
function stream_write(mixed $socket, string $data, int $length)
{
    for ($written = 0; $written < strlen($data); $written += $length) {
        $fwrite = fwrite($socket, substr($data, $written));
        if ($fwrite === false) {
            continue;
        }
    }
}
