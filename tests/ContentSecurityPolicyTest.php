<?php

use PHPUnit\Framework\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Set up necessary environment for the tests
    }

    public function testSaveAndRetrieveCSPSettings()
    {
        // Save CSP settings
        $cspSettings = array(
            'default_src' => "'self'",
            'script_src' => "'self'",
            'style_src' => "'self'",
            'img_src' => "'self'",
            'connect_src' => "'self'",
            'font_src' => "'self'",
            'object_src' => "'none'",
            'media_src' => "'self'",
            'frame_src' => "'none'",
        );
        SucuriScanOption::updateOption(':headers_csp_options', $cspSettings);

        // Retrieve CSP settings
        $retrievedSettings = SucuriScanOption::getOption(':headers_csp_options');

        // Assert that the saved settings match the retrieved settings
        $this->assertEquals($cspSettings, $retrievedSettings);
    }

    public function testSetCSPHeaders()
    {
        // Save CSP settings
        $cspSettings = array(
            'default_src' => "'self'",
            'script_src' => "'self'",
            'style_src' => "'self'",
            'img_src' => "'self'",
            'connect_src' => "'self'",
            'font_src' => "'self'",
            'object_src' => "'none'",
            'media_src' => "'self'",
            'frame_src' => "'none'",
        );
        SucuriScanOption::updateOption(':headers_csp_options', $cspSettings);

        // Set CSP headers
        $cacheHeaders = new SucuriScanCacheHeaders();
        $cacheHeaders->setCSPHeaders();

        // Assert that the CSP headers are set correctly
        $expectedHeader = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; font-src 'self'; object-src 'none'; media-src 'self'; frame-src 'none';";
        $this->assertEquals($expectedHeader, xdebug_get_headers()['Content-Security-Policy']);
    }

    public function testDisplayAndUpdateCSPSettingsInFrontend()
    {
        // Save CSP settings
        $cspSettings = array(
            'default_src' => "'self'",
            'script_src' => "'self'",
            'style_src' => "'self'",
            'img_src' => "'self'",
            'connect_src' => "'self'",
            'font_src' => "'self'",
            'object_src' => "'none'",
            'media_src' => "'self'",
            'frame_src' => "'none'",
        );
        SucuriScanOption::updateOption(':headers_csp_options', $cspSettings);

        // Simulate frontend display and update
        $displayedSettings = sucuriscan_settings_csp_options(true);
        $this->assertStringContainsString("'self'", $displayedSettings);

        // Update CSP settings
        $_POST['sucuriscan_default_src_value'] = "'self' https://example.com";
        sucuriscan_settings_csp_options(true);

        // Retrieve updated CSP settings
        $updatedSettings = SucuriScanOption::getOption(':headers_csp_options');
        $this->assertEquals("'self' https://example.com", $updatedSettings['default_src']);
    }
}
