<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Craft Framework - System Status</title>
    <link rel="stylesheet" href="<?= resource('css/main.css') ?>">
</head>

<body>
    <div class="container">
        <button class="theme-toggle" id="themeToggle" title="Toggle light/dark mode">🌙</button>
        <div class="header">
            <div style="display: flex; align-items: baseline; justify-content: center; gap: 8px;">
                <h1 style="margin: 0;">Craft Framework</h1>
                <small
                    style="font-size: 1rem; color: #888; margin-left: 6px;">v<?= \Craft\Application\App::version ?></small>
            </div>
            <p>Build fast. Build smart. Build beautiful.</p>
            <small style="color: #888;">This is a development edition for Craft Framework.</small>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Environment</h2>
                <div class="status-item">
                    <div class="status-text <?= $phpOk ? 'text-success' : 'text-error' ?>">PHP version is
                        <?= $phpVersion ?>
                        <?= $phpOk ? 'and meets minimum requirements of Craft (Lite Edition)' : ", but Craft (Lite Edition) require PHP $phpMinVersion" ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-text <?= $mbstringLoaded ? 'text-success' : 'text-error' ?>">mbstring extension
                        is <?= $mbstringLoaded ? 'loaded' : 'not loaded' ?></div>
                </div>
                <div class="status-item">
                    <div class="status-text <?= $opensslLoaded ? 'text-success' : 'text-error' ?>">openssl extension is
                        <?= $opensslLoaded ? 'loaded' : 'not loaded' ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-text <?= $mysqliLoaded ? 'text-success' : 'text-error' ?>">mysqli extension is
                        <?= $mysqliLoaded ? 'loaded' : 'not loaded' ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Filecache</h2>
                <div class="status-item">
                    <div class="status-text <?= $logsWritable ? 'text-success' : 'text-error' ?>">Logs directory is
                        <?= $logsWritable ? 'writable' : 'not writable or not exists' ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-text <?= $cacheWritable ? 'text-success' : 'text-error' ?>">Cache directory is
                        <?= $cacheWritable ? 'writable' : 'not writable or not exists' ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-text <?= $configWritable ? 'text-success' : 'text-error' ?>">Config directory is
                        <?= $configWritable ? 'writable' : 'not writable or not exists' ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Mysql Database</h2>
                <div class="status-item">
                    <div class="status-text <?= $dbConnected ? 'text-success' : 'text-error' ?>">
                        <?= $dbConnected ? 'Successfully connected MySQL to database' : 'Failed to connect MySQL to database: ' . htmlspecialchars($dbError) ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-text">Database: <?= htmlspecialchars($dbname) ?></div>
                </div>
                <h2>Sqlite3 Database</h2>
                <div class="status-item">
                    <div class="status-text <?= $dbConnected2 ? 'text-success' : 'text-error' ?>">
                        <?= $dbConnected2 ? 'Successfully connected to SQLite3 database' : 'Failed to connect to SQLite3 database: ' . htmlspecialchars($dbError) ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-text">Database: <?= htmlspecialchars($sqlitefile) ?></div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>&copy; 2025 Craft Framework. All rights reserved.</p>
            <p>Developed with ❤️ by <a href="https://github.com/datahihi1" target="_blank">Datahihi1</a></p>
        </div>
    </div>

    <script src="<?= resource('js/main.js') ?>"></script>
</body>

</html>