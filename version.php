<?php
function getGitVersionInfo()
{
    // Получаем последний тег (версию)
    $version = shell_exec('git describe --tags --abbrev=0');

    // Получаем дату последнего коммита
    $lastCommitDate = shell_exec('git log -1 --format=%cd --date=short 2> /dev/null');

    // Получаем хеш последнего коммита (короткий)
    $commitHash = shell_exec('git rev-parse --short HEAD 2> /dev/null');

    // Получаем ветку
    $branch = shell_exec('git rev-parse --abbrev-ref HEAD 2> /dev/null');

    return [
        'version' => $version ? trim($version) : 'dev',
        'last_updated' => $lastCommitDate ? trim($lastCommitDate) : date('Y-m-d'),
        'commit' => $commitHash ? trim($commitHash) : 'unknown',
        'branch' => $branch ? trim($branch) : 'unknown'
    ];
}

// Кэшируем информацию на 1 час
$versionInfo = getGitVersionInfo();
