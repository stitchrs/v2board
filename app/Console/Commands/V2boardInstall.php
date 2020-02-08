<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Utils\Helper;
use Illuminate\Support\Facades\DB;

class V2boardInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'v2board:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'v2board 安装';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (\File::exists(base_path() . '/.lock')) {
            abort(500, 'V2board 已安装，如需重新安装请删除目录下.lock文件');
        }
        if (!\File::exists(base_path() . '/.env')) {
            if (!copy('/.env.example', '.env')) {
                abort(500, '复制环境文件失败，请检查目录权限');
            }
        }
        $this->saveToEnv([
            'APP_KEY' => 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC')),
            'DB_HOST' => $this->ask('请输入数据库地址（默认:localhost）', 'localhost'),
            'DB_DATABASE' => $this->ask('请输入数据库名'),
            'DB_USERNAME' => $this->ask('请输入数据库用户名'),
            'DB_PASSWORD' => $this->ask('请输入数据库密码')
        ]);
        \Artisan::call('config:clear');
        \Artisan::call('config:cache');
        if (!DB::connection()->getPdo()) {
            abort(500, '数据库连接失败');
        }
        $file = \File::get(base_path() . '/database/install.sql');
        if (!$file) {
            abort(500, '数据库文件不存在');
        }
        $sql = str_replace("\n", "", $file);
        $sql = preg_split("/;/", $sql);
        if (!is_array($sql)) {
            abort(500, '数据库文件格式有误');
        }
        $this->info('正在导入数据库请稍等...');
        foreach ($sql as $item) {
            try {
                DB::select(DB::raw($item));
            } catch (\Exception $e) {
            }
        }
        $email = '';
        while (!$email) {
            $email = $this->ask('请输入管理员邮箱?');
        }
        $password = '';
        while (!$password) {
            $password = $this->ask('请输入管理员密码?');
        }
        if (!$this->registerAdmin($email, $password)) {
            abort(500, '管理员账号注册失败，请重试');
        }

        $this->info('一切就绪');
        \File::put(base_path() . '/.lock', time());
    }

    private function registerAdmin($email, $password)
    {
        $user = new User();
        $user->email = $email;
        if (strlen($password) < 8) {
            abort(500, '管理员密码长度最小为8位字符');
        }
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->v2ray_uuid = Helper::guid(true);
        $user->token = Helper::guid();
        $user->is_admin = 1;
        return $user->save();
    }

    private function saveToEnv($data = [])
    {
        foreach($data as $key => $value) {
            set_env_var($key, $value);
        }
        return true;
    }
}
