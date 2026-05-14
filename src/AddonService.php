<?php

declare(strict_types=1);

namespace creatcode\easyaddons;

use creatcode\easyaddons\addons\command\AddonCommand;
use creatcode\easyaddons\addons\command\TenantAddonCommand;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Event;
use think\facade\Route;
use think\helper\Str;

/**
 * 插件服务注册类
 *
 * 负责初始化插件目录、注册插件事件、注册插件路由和插件管理命令。
 */
class AddonService extends \think\Service
{
    /**
     * 注册插件基础能力
     *
     * 该阶段只处理插件目录和事件监听，路由注册延迟到 RouteLoaded 阶段执行
     */
    public function register()
    {
        // 插件目录
        !defined('ADDON_PATH') && define('ADDON_PATH', app()->getRootPath() . 'addons' . DIRECTORY_SEPARATOR);
        // 如果插件目录不存在则创建
        if (!is_dir(ADDON_PATH)) {
            @mkdir(ADDON_PATH, 0755, true);
        }
        //注册插件事件
        $this->addon_event();
    }

    /**
     * 注册插件事件
     */
    protected function addon_event()
    {
        $hooks = Config::get('app.app_debug') ? [] : Cache::get('hooks', []);

        if (empty($hooks)) {
            $hooks = (array) Config::get('addons.hooks');

            // 初始化插件钩子监听
            foreach ($hooks as $key => $values) {
                $values = is_string($values) ? explode(',', $values) : (array) $values;

                $hooks[$key] = array_values(array_filter(array_map(function ($addon) use ($key) {
                    $class = get_addon_class($addon);

                    return $class ? [$class, Str::camel($key)] : null;
                }, $values)));
            }

            Cache::set('hooks', $hooks);
        }

        if (!$hooks) {
            return;
        }

        // 先注册监听，再按需触发初始化事件
        Event::listenEvents($hooks);

        if (isset($hooks['app_init'])) {
            Event::trigger('app_init', app());
        }
    }


    /**
     * 获取插件路由中间件
     *
     * 中间件类可能随项目结构或 ThinkPHP 版本变化，只注册当前环境真实存在的类。
     */
    private function addon_middlewares(): array
    {
        $middlewares = [
            'app\\common\\middleware\\CommonInit',
            'think\\middleware\\LoadLangPack',
        ];

        // 只注册真实存在的中间件
        return array_values(array_filter($middlewares, 'class_exists'));
    }

    /**
     * 注册插件路由
     */
    private function addon_route()
    {
        $execute = '\\creatcode\\easyaddons\\addons\\Route::execute';
        $middlewares = $this->addon_middlewares();

        Route::rule('addons/:addon/[:controller]/[:action]', $execute)
            ->middleware($middlewares);

        // 注册自定义插件路由
        $routeArr = (array) Config::get('addons.route');
        foreach ($routeArr as $k => $v) {
            if (is_array($v)) {
                if (empty($v['domain']) || empty($v['rule']) || !is_array($v['rule'])) {
                    continue;
                }

                $domain = $v['domain'];
                $drules = [];

                foreach ($v['rule'] as $m => $n) {
                    $parts = explode('/', (string) $n);
                    if (count($parts) !== 3) {
                        continue;
                    }

                    [$addon, $controller, $action] = $parts;
                    $drules[$m] = [
                        'addon'      => $addon,
                        'controller' => $controller,
                        'action'     => $action,
                        'indomain'   => 1,
                    ];
                }

                if (!$drules) {
                    continue;
                }

                Route::domain($domain, function () use ($drules, $execute, $middlewares) {
                    // 动态注册域名路由规则
                    foreach ($drules as $k => $rule) {
                        Route::rule($k, $execute)
                            ->middleware($middlewares)
                            ->name($k)
                            ->completeMatch(true)
                            ->append($rule);
                    }
                });
            } else {
                if (!$v) {
                    continue;
                }

                $parts = explode('/', (string) $v);
                if (count($parts) !== 3) {
                    continue;
                }

                [$addon, $controller, $action] = $parts;
                Route::rule($k, $execute)
                    ->middleware($middlewares)
                    ->name($k)
                    ->completeMatch(true)
                    ->append(['addon' => $addon, 'controller' => $controller, 'action' => $action]);
            }
        }
    }

    /**
     * 执行服务
     *
     * @return mixed
     */
    public function boot()
    {
        // 路由注册
        $this->registerRoutes(function () {
            $this->addon_route();
        });

        // 注册插件管理命令
        $this->commands([
            AddonCommand::class,
            TenantAddonCommand::class,
        ]);
    }
}
