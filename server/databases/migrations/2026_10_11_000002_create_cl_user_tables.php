<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cl_user', function (Blueprint $table) {
            $table->comment('App 用户（docs/04 第 1 节）');
            $table->bigIncrements('id');
            $table->string('account', 32)->unique()->comment('登录账号：自己注册=手机号；批量开号=系统生成');
            $table->string('phone', 20)->nullable()->unique()->comment('手机号（批量号可为空）');
            $table->string('password', 255)->comment('bcrypt');
            $table->tinyInteger('gender')->comment('1 男 2 女，注册后用户不可改');
            $table->string('nickname', 32)->comment('已审核通过的昵称');
            $table->string('avatar', 255)->default('')->comment('已审核通过的头像 URL');
            $table->string('bio', 255)->default('')->comment('已审核通过的自我介绍');
            $table->date('birthday')->nullable()->comment('生日');
            $table->string('city', 32)->default('')->comment('城市');
            $table->smallInteger('height')->nullable()->comment('身高 cm');
            $table->string('job', 32)->default('')->comment('职业');
            $table->string('education', 16)->default('')->comment('学历');
            $table->string('dating_purpose', 16)->default('')->comment('认真交友/兴趣搭子/长期关系');
            $table->json('tags')->nullable()->comment('兴趣标签 ID 数组');
            $table->tinyInteger('level')->default(1)->comment('女用户等级 1~5');
            $table->tinyInteger('level_locked')->default(0)->comment('1 = 锁定等级');
            $table->bigInteger('total_earned_points')->default(0)->comment('累计收益积分（只算私信+礼物分成）');
            $table->dateTime('vip_expire_at')->nullable()->comment('会员到期时间');
            $table->tinyInteger('real_name_status')->default(0)->comment('0 未提交 1 审核中 2 通过 3 驳回');
            $table->tinyInteger('status')->default(1)->comment('1 正常 2 封禁 3 已注销');
            $table->dateTime('ban_until')->nullable()->comment('封禁到期（null + status=2 表示永久）');
            $table->string('ban_reason', 255)->nullable()->comment('封禁原因');
            $table->tinyInteger('source')->default(1)->comment('1 自主注册 2 后台批量开号');
            $table->string('batch_no', 32)->nullable()->comment('批量开号批次号');
            $table->tinyInteger('show_in_nearby')->default(1)->comment('展示给附近的人');
            $table->tinyInteger('show_in_recommend')->default(1)->comment('P27「在线接听」开关');
            $table->dateTime('last_active_at')->nullable()->comment('最后活跃时间');
            $table->tinyInteger('login_fail_count')->default(0)->comment('连续登录失败次数');
            $table->dateTime('locked_until')->nullable()->comment('密码错误锁定到期时间');
            $table->string('last_login_ip', 45)->default('')->comment('最后登录 IP');
            $table->string('last_login_device', 64)->default('')->comment('设备 ID（只记录，不限制多号）');
            $table->datetimes();
            $table->index(['gender', 'status', 'last_active_at'], 'cl_user_gender_status_active_index');
            $table->index('city', 'cl_user_city_index');
            $table->index('batch_no', 'cl_user_batch_no_index');
        });

        Schema::create('cl_coin_wallet', function (Blueprint $table) {
            $table->comment('男用户金币钱包（docs/04 第 4 节）');
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->unique()->comment('用户 ID');
            $table->bigInteger('balance')->default(0)->comment('可用金币，不允许为负');
            $table->bigInteger('total_recharge')->default(0)->comment('累计充值金币');
            $table->bigInteger('total_spent')->default(0)->comment('累计消费金币');
            $table->datetimes();
        });

        Schema::create('cl_point_wallet', function (Blueprint $table) {
            $table->comment('女用户积分钱包（docs/04 第 4 节）');
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->unique()->comment('用户 ID');
            $table->bigInteger('balance')->default(0)->comment('可用积分，不允许为负');
            $table->bigInteger('frozen')->default(0)->comment('冻结中（提现审核中）');
            $table->bigInteger('total_withdrawn')->default(0)->comment('累计已提现积分');
            $table->datetimes();
        });

        // 余额非负约束（docs/04 第 4 节）。**只在 MySQL 8.0.16+ 上执行** ——
        // 5.7 的 ALTER TABLE 语法里没有 `ADD CONSTRAINT <名字> CHECK (...)`，直接跑会报语法错误、
        // 把整条迁移链断在这儿；5.7 的 CHECK 本身也只解析不生效。应用层已经有校验
        // （`CoinWalletService`/`PointWalletService` 在事务里判断，`check-money.sh` 守着调用方式），
        // 这里只是 DB 层的兜底，5.7 上跳过不损失功能。
        if ($this->supportsCheckConstraint()) {
            Db::statement('ALTER TABLE `cl_coin_wallet` ADD CONSTRAINT `chk_cl_coin_wallet_balance` CHECK (`balance` >= 0)');
            Db::statement('ALTER TABLE `cl_point_wallet` ADD CONSTRAINT `chk_cl_point_wallet_balance` CHECK (`balance` >= 0)');
            Db::statement('ALTER TABLE `cl_point_wallet` ADD CONSTRAINT `chk_cl_point_wallet_frozen` CHECK (`frozen` >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cl_point_wallet');
        Schema::dropIfExists('cl_coin_wallet');
        Schema::dropIfExists('cl_user');
    }
    /**
     * 这个数据库支不支持 `ALTER TABLE ... ADD CONSTRAINT <名字> CHECK (...)`。
     *
     * 只有 MySQL **8.0.16+** 才真正支持这一写法并强制执行；5.7 的 ALTER TABLE 语法里
     * 只有不带约束名的 `ADD CHECK (...)`，带 CONSTRAINT 会报语法错误。
     * 用 `SELECT VERSION()` 判断，认不出就当不支持（宁可少一条兜底，也不让迁移跑不过）。
     */
    private function supportsCheckConstraint(): bool
    {
        try {
            $row = Db::select('SELECT VERSION() AS v')[0] ?? null;
        } catch (Throwable) {
            return false;
        }

        $version = is_object($row) ? ($row->v ?? '') : (is_array($row) ? ($row['v'] ?? '') : '');

        // 版本串形如 "8.0.44-0ubuntu0.24.04.1" / "5.7.44-log"，只取前三段数字
        return preg_match('/^(\d+\.\d+\.\d+)/', (string) $version, $m) === 1
            && version_compare($m[1], '8.0.16', '>=');
    }
};
