<?php
namespace App\Controller;

use Craft\Application\Router;

class WelcomeController extends Controller
{
    public function welcome()
    {
        /**
         * Client PHP version
         */
        $phpVersion = phpversion();
        /**
         * Minimum PHP version required
         * 
         * Craft requires PHP 7.1 or higher
         */
        $phpMinVersion = '7.1.0';
        /** Check PHP version is ok ? */
        $phpOk = version_compare($phpVersion, $phpMinVersion, '>=');

        $dbConnected = false;
        $dbError = '';
        $host = env('DB_HOST');
        $user = env('DB_USER');
        $pass = env('DB_PASS');
        $dbname = env('DB_NAME');
        $sqlitefile = env('DB_SQLITE_FILE');
        // dump($host, $user, $pass, $dbname, $sqlitefile);

        $mbstringLoaded = extension_loaded('mbstring');
        $opensslLoaded = extension_loaded('openssl');
        $mysqliLoaded = extension_loaded('mysqli'); // Sửa lại tên extension cho đúng

        // Kiểm tra quyền ghi thư mục
        $tempDir = __DIR__ . '/../../public'; // dùng public làm temp
        $logsDir = ROOT_DIR . '/public/logs';
        $tempWritable = is_writable($tempDir);
        $logsWritable = is_writable($logsDir);

        // Kiểm tra FileEngine caching (giả sử chỉ cần file config tồn tại)
        $fileEngineConfigured = file_exists(__DIR__ . '/../../public/config/light.config.txt');

        $cacheDir = __DIR__ . '/../../public/cache';
        $configDir = __DIR__ . '/../../public/config';
        $cacheWritable = is_writable($cacheDir);
        $configWritable = is_writable($configDir);

        try {
            $mysqli = new \mysqli($host, $user, $pass, $dbname);
            if ($mysqli->connect_errno) {
                throw new \Exception($mysqli->connect_error);
            }
            $dbConnected = true;
        } catch (\Exception $e) {
            $dbConnected = false;
            $dbError = $e->getMessage();
        }

        try{
            $sqlite = new \SQLite3(ROOT_DIR . '/public/' . $sqlitefile . '.db');
            if (!$sqlite) {
                throw new \Exception('Could not connect to SQLite database');
            }
            $dbConnected2 = true; 
        } catch (\Exception $e) {
            $dbConnected2 = false;
            $dbError = $e->getMessage();
        }

        return \Craft\Application\View::render('welcome', [
            'phpVersion' => $phpVersion,
            'phpMinVersion' => $phpMinVersion,
            'phpOk' => $phpOk,
            'mbstringLoaded' => $mbstringLoaded,
            'opensslLoaded' => $opensslLoaded,
            'mysqliLoaded' => $mysqliLoaded,
            'tempWritable' => $tempWritable,
            'logsWritable' => $logsWritable,
            'cacheWritable' => $cacheWritable,
            'configWritable' => $configWritable,
            'fileEngineConfigured' => $fileEngineConfigured,
            'dbConnected' => $dbConnected,
            'dbConnected2' => $dbConnected2,
            'dbError' => $dbError,
            'dbname' => $dbname,
            'sqlitefile' => $sqlitefile,
        ]);
    }

    #[Router(method: 'GET', router: '/test', api: true, name: 'test.route')]
    public function test()
    {
        return 'This is a test route';
    }
}