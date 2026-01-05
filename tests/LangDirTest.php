<?php

namespace ShaunTheGeek\LaravelLocaleSetterTests;

use Illuminate\Support\Facades\File;
use ShaunTheGeek\LaravelLocaleSetter\Middleware\DetectLocale;

class LangDirTest extends TestCase
{
    private $langDirs = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试用的临时语言目录
        $this->createTestLangDirectories();
    }

    protected function tearDown(): void
    {
        // 删除测试用的临时语言目录
        $this->cleanupTestLangDirectories();
        parent::tearDown();
    }

    private function cleanupTestLangDirectories()
    {
        $langPath = $this->getTestLangPath();
        if (File::exists($langPath)) {
            File::deleteDirectory($langPath);
        }
    }

    /**
     * 创建测试用的语言目录
     */
    private function createTestLangDirectories()
    {
        $langPath = $this->getTestLangPath();

        // 确保语言目录存在
        if (! File::exists($langPath)) {
            File::makeDirectory($langPath, 0755, true);
        }

        // 创建几个测试用的语言目录
        $testLocales = ['en', 'zh_CN', 'zh_TW', 'ja', 'ko', 'fr', 'de', 'es', 'cmn_Hans', 'cmn_Hant'];

        foreach ($testLocales as $locale) {
            $localeDir = $langPath.'/'.$locale;
            if (! File::exists($localeDir)) {
                File::makeDirectory($localeDir, 0755, true);

                // 创建一个简单的语言文件以确保目录被识别
                File::put($localeDir.'/test.php', "<?php\nreturn [\n    'hello' => 'Hello',\n];\n");
            }
            $this->langDirs[] = $localeDir;
        }
    }

    /**
     * 获取测试用的语言目录路径
     */
    private function getTestLangPath()
    {
        return $this->app->langPath();
    }

    /**
     * 测试 getLocales 方法能够正确扫描语言目录
     */
    public function test_get_locales_scans_lang_directory()
    {
        // 创建 DetectLocale 实例
        $detectLocale = new DetectLocale($this->app);

        // 调用 getLocales 方法
        $locales = $detectLocale->getLocales();

        // 验证扫描到的语言目录是否正确
        $expectedLocales = ['en', 'zh_CN', 'zh_TW', 'ja', 'ko', 'fr', 'de', 'es', 'cmn_Hans', 'cmn_Hant'];

        foreach ($expectedLocales as $expectedLocale) {
            $this->assertContains($expectedLocale, $locales, "Locale {$expectedLocale} should be found in scanned locales");
        }

        // 验证返回的数组长度
        $this->assertCount(count($expectedLocales), array_intersect($expectedLocales, $locales));
    }

    /**
     * 测试当配置中指定了 locales 时，不会扫描目录
     */
    public function test_get_locales_uses_configured_locales_when_specified()
    {
        // 设置配置中的 locales
        $configuredLocales = ['en', 'fr', 'de'];
        $this->app['config']->set('locale.locales', $configuredLocales);

        // 创建 DetectLocale 实例
        $detectLocale = new DetectLocale($this->app);

        // 调用 getLocales 方法
        $locales = $detectLocale->getLocales();

        // 验证返回的是配置中的语言，而不是扫描到的
        $this->assertEquals($configuredLocales, $locales);
    }

    /**
     * 测试 getLocales 方法在空目录下的行为
     */
    public function test_get_locales_with_empty_lang_directory()
    {
        $this->cleanupTestLangDirectories();

        // 创建 DetectLocale 实例
        $detectLocale = new DetectLocale($this->app);

        // 调用 getLocales 方法
        $locales = $detectLocale->getLocales();

        // 验证返回的是空数组
        $this->assertIsArray($locales);
        $this->assertEmpty($locales);
    }

    /**
     * 测试 lang2locale 方法能够正确匹配扫描到的语言
     */
    public function test_lang2locale_matches_scanned_locales()
    {
        // 创建 DetectLocale 实例
        $detectLocale = new DetectLocale($this->app);

        // 获取扫描到的语言
        $locales = $detectLocale->getLocales();

        // 测试各种语言代码匹配
        $this->assertNotNull(DetectLocale::lang2locale('en', [], $locales), 'English locale should match');
        $this->assertNotNull(DetectLocale::lang2locale('zh-CN', [], $locales), 'Simplified Chinese locale should match');
        $this->assertNotNull(DetectLocale::lang2locale('zh_TW', [], $locales), 'Traditional Chinese locale should match');
        $this->assertNotNull(DetectLocale::lang2locale('ja', [], $locales), 'Japanese locale should match');
        $this->assertNotNull(DetectLocale::lang2locale('fr', [], $locales), 'French locale should match');
    }
}
