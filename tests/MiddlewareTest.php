<?php

namespace ShaunTheGeek\LaravelLocaleSetterTests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use ShaunTheGeek\LaravelLocaleSetter\Middleware\DetectLocale;

class MiddlewareTest extends TestCase
{
    private function getTestLangPath()
    {
        return $this->app->langPath();
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function setupTestLangDirectories()
    {
        // 创建测试用的语言目录
        $langPath = $this->getTestLangPath();

        if (! File::exists($langPath)) {
            File::makeDirectory($langPath, 0755, true);
        }

        // 创建几个测试用的语言目录和JSON语言文件
        $testLocales = ['en', 'zh_CN', 'zh_TW', 'ja', 'fr', 'de'];

        foreach ($testLocales as $locale) {
            $localeDir = $langPath.'/'.$locale;
            if (! File::exists($localeDir)) {
                File::makeDirectory($localeDir, 0755, true);

                // 创建一个简单的语言文件
                File::put($localeDir.'/test.php', "<?php\nreturn [\n    'hello' => 'Hello',\n];\n");
            }
        }

        // 创建JSON语言文件
        File::put($langPath.'/en.json', '{"hello": "Hello", "greeting": "Greetings!", "welcome": "Welcome"}');
        File::put($langPath.'/zh_CN.json', '{"hello": "你好", "greeting": "您好!", "welcome": "欢迎"}');
        File::put($langPath.'/zh_TW.json', '{"hello": "你好", "greeting": "您好!", "welcome": "歡迎"}');
        File::put($langPath.'/ja.json', '{"hello": "こんにちは", "greeting": "こんにちは!", "welcome": "ようこそ"}');
        File::put($langPath.'/fr.json', '{"hello": "Bonjour", "greeting": "Salutations!", "welcome": "Bienvenue"}');
        File::put($langPath.'/de.json', '{"hello": "Hallo", "greeting": "Grüße!", "welcome": "Willkommen"}');

        return $langPath;
    }

    protected function tearDown(): void
    {
        // 清理测试创建的语言目录
        $langPath = $this->getTestLangPath();
        if (File::exists($langPath)) {
            File::deleteDirectory($langPath);
        }

        parent::tearDown();
    }

    /**
     * 测试中间件能正确处理 HTTP Accept-Language 头
     */
    public function test_middleware_handles_accept_language_header()
    {
        // 设置默认语言为 'en'
        $this->app['config']->set('app.locale', 'en');

        $this->setupTestLangDirectories();

        // 创建路由并应用中间件
        $this->app['router']->get('/test', function () {
            return response('Test response');
        })->middleware(DetectLocale::class);

        // 创建一个请求，带有 Accept-Language 头
        $response = $this->withServerVariables([
            'HTTP_ACCEPT_LANGUAGE' => 'zh-CN,zh;q=0.9,en;q=0.8',
        ])->get('/test');

        // 验证应用的语言被正确设置
        $this->assertEquals('zh_CN', $this->app->getLocale());
    }

    /**
     * 测试中间件使用扫描到的语言目录
     */
    public function test_middleware_uses_scanned_locales()
    {
        // 设置默认语言为 'en'
        $this->app['config']->set('app.locale', 'en');

        $this->setupTestLangDirectories();

        // 清空配置中的 locales，让中间件扫描目录
        $this->app['config']->set('locale.locales', []);
        $this->app['config']->set('locale.map', []);

        // 创建路由并应用中间件
        $this->app['router']->get('/test-scanned', function () {
            return response('Test response');
        })->middleware(DetectLocale::class);

        // 创建一个请求，带有 Accept-Language 头，请求一个存在的语言
        $response = $this->withServerVariables([
            'HTTP_ACCEPT_LANGUAGE' => 'fr-CH,fr;q=0.9,en;q=0.8,de;q=0.7',
        ])->get('/test-scanned');

        // 验证应用的语言被正确设置为 'fr' (因为 fr 在我们的测试语言目录中)
        $this->assertEquals('fr', $this->app->getLocale());
    }

    /**
     * 测试中间件使用配置的语言映射
     */
    public function test_middleware_uses_locale_map()
    {
        // 设置默认语言为 'en'
        $this->app['config']->set('app.locale', 'en');

        $this->setupTestLangDirectories();

        // 设置语言映射
        $this->app['config']->set('locale.map', [
            'zh_CN' => 'zh_CN',
            'en_US' => 'en',
        ]);

        // 清空配置中的 locales
        $this->app['config']->set('locale.locales', []);
        // 创建路由并应用中间件
        $this->app['router']->get('/test-map', function () {
            return response('Test response');
        })->middleware(DetectLocale::class);

        // 创建一个请求，带有 Accept-Language 头
        $response = $this->withServerVariables([
            'HTTP_ACCEPT_LANGUAGE' => 'zh-CN,zh;q=0.9,en;q=0.8',
        ])->get('/test-map');

        $this->assertEquals(200, $response->getStatusCode());

        // 验证应用的语言被正确设置为 'zh_CN' (通过映射)
        $this->assertEquals('zh_CN', $this->app->getLocale());
    }

    /**
     * 测试中间件处理不存在的语言
     */
    public function test_middleware_handles_unknown_language()
    {
        // 设置默认语言为 'en'
        $this->app['config']->set('app.locale', 'en');

        $this->setupTestLangDirectories();

        // 清空配置中的 locales 和 map
        $this->app['config']->set('locale.locales', []);
        $this->app['config']->set('locale.map', []);

        // 创建中间件实例并处理请求（重要：在设置语言目录后创建中间件）
        $middleware = new DetectLocale($this->app);

        // 创建一个请求，带有 Accept-Language 头，请求一个不存在的语言
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'unknown-US,unknown;q=0.9,es;q=0.8');

        $response = $middleware->handle($request, function ($req) {
            return response('Test response');
        });

        // 验证应用的语言被设置为默认语言 'en'
        $this->assertEquals('en', $this->app->getLocale());
    }

    /**
     * 测试中间件处理空的 Accept-Language 头
     */
    public function test_middleware_handles_empty_accept_language()
    {
        // 设置默认语言为 'en'
        $this->app['config']->set('app.locale', 'en');

        $this->setupTestLangDirectories();

        // 创建一个请求，不带 Accept-Language 头
        $request = Request::create('/', 'GET');

        // 创建中间件实例并处理请求
        $middleware = new DetectLocale($this->app);
        $response = $middleware->handle($request, function ($req) {
            return response('Test response');
        });

        // 验证应用的语言被设置为默认语言 'en'
        $this->assertEquals('en', $this->app->getLocale());
    }

    /**
     * 测试 getFirstLang 静态方法
     */
    public function test_get_first_lang_static_method()
    {
        // 测试简单的语言代码
        $this->assertEquals('en-US', DetectLocale::getFirstLang('en-US,en;q=0.9'));

        // 测试复杂的 Accept-Language 头
        $this->assertEquals('zh-CN', DetectLocale::getFirstLang('zh-CN,zh;q=0.9,en;q=0.8'));

        // 测试带有多参数的头
        $this->assertEquals('fr-CH', DetectLocale::getFirstLang('fr-CH, fr;q=0.9, en;q=0.8, de;q=0.7'));

        // 测试单个语言
        $this->assertEquals('en', DetectLocale::getFirstLang('en'));

        // 测试空值
        $this->assertFalse(DetectLocale::getFirstLang(''));
    }

    /**
     * 测试中间件与控制器的翻译功能
     */
    public function test_middleware_with_controller_translations()
    {
        // 设置默认语言为 'en'
        $this->app['config']->set('app.locale', 'en');

        $this->setupTestLangDirectories();

        // 创建路由和控制器
        $this->app['router']->get('/test-translation', function () {
            $hello = __('hello');

            return response()->json(['message' => $hello]);
        })->middleware(DetectLocale::class);

        // 测试英文
        $response = $this->withServerVariables([
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
        ])->get('/test-translation');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Hello']);

        // 测试中文
        $response = $this->withServerVariables([
            'HTTP_ACCEPT_LANGUAGE' => 'zh-CN,zh;q=0.9,en;q=0.8',
        ])->get('/test-translation');

        $response->assertStatus(200);
        $response->assertJson(['message' => '你好']);
    }

    /**
     * 测试控制器使用不同语言的翻译
     */
    public function test_controller_translations_with_different_locales()
    {
        // 设置默认语言为 'en'
        $this->app['config']->set('app.locale', 'en');

        $this->setupTestLangDirectories();

        // 创建路由测试不同语言
        $this->app['router']->get('/test-greeting', function () {
            $greeting = __('greeting');

            return response()->json(['greeting' => $greeting]);
        })->middleware(DetectLocale::class);

        // 测试法语
        $response = $this->withServerVariables([
            'HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9,en;q=0.8',
        ])->get('/test-greeting');

        $response->assertStatus(200);
        $response->assertJson(['greeting' => 'Salutations!']);

        // 测试德语
        $response = $this->withServerVariables([
            'HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9,en;q=0.8',
        ])->get('/test-greeting');

        $response->assertStatus(200);
        $response->assertJson(['greeting' => 'Grüße!']);
    }

    /**
     * 测试控制器使用JSON语言文件的翻译
     */
    public function test_controller_uses_json_language_files()
    {
        // 设置默认语言为 'en'
        $this->app['config']->set('app.locale', 'en');

        $this->setupTestLangDirectories();

        // 创建路由测试JSON语言文件
        $this->app['router']->get('/test-welcome', function () {
            $welcome = __('welcome');

            return response()->json(['welcome' => $welcome]);
        })->middleware(DetectLocale::class);

        // 测试繁体中文
        $response = $this->withServerVariables([
            'HTTP_ACCEPT_LANGUAGE' => 'zh-TW,zh;q=0.9,en;q=0.8',
        ])->get('/test-welcome');

        $response->assertStatus(200);
        $response->assertJson(['welcome' => '歡迎']);

        // 测试日语
        $response = $this->withServerVariables([
            'HTTP_ACCEPT_LANGUAGE' => 'ja-JP,ja;q=0.9,en;q=0.8',
        ])->get('/test-welcome');

        $response->assertStatus(200);
        $response->assertJson(['welcome' => 'ようこそ']);
    }
}
