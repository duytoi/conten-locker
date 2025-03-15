<?php
class Test_Shortcodes extends WP_UnitTestCase {
    private $download_shortcode;
    private $protection_shortcode;
    private $download_service;
    private $test_file;

    public function setUp() {
        parent::setUp();
        $this->download_shortcode = new WCL_Download_Shortcode();
        $this->protection_shortcode = new WCL_Protection_Shortcode();
        $this->download_service = new WCL_Download_Service();

        // Create test file
        $this->test_file = wp_upload_dir()['path'] . '/test-shortcode.txt';
        file_put_contents($this->test_file, 'Test content');
    }

    public function tearDown() {
        if (file_exists($this->test_file)) {
            unlink($this->test_file);
        }
        parent::tearDown();
    }

    public function test_download_shortcode() {
        // Create a test download
        $download_id = $this->download_service->create_download(
            array(
                'title' => 'Test Download',
                'status' => 'active'
            ),
            array(
                'tmp_name' => $this->test_file,
                'name' => 'test-shortcode.txt'
            )
        );

        // Test shortcode output
        $output = $this->download_shortcode->render(array(
            'id' => $download_id,
            'text' => 'Download Now'
        ));

        $this->assertStringContainsString('Download Now', $output);
        $this->assertStringContainsString('wcl-download-button', $output);
        $this->assertStringContainsString((string)$download_id, $output);
    }

    public function test_protection_shortcode() {
        $content = 'Protected content';
        $preview = 'Preview text';

        // Test password protection
        $output = $this->protection_shortcode->render(
            array(
                'type' => 'password',
                'password' => 'test123',
                'preview' => $preview
            ),
            $content
        );

        $this->assertStringContainsString($preview, $output);
        $this->assertStringContainsString('wcl-protected-content', $output);
        $this->assertStringNotContainsString($content, $output);

        // Test countdown protection
        $output = $this->protection_shortcode->render(
            array(
                'type' => 'countdown',
                'countdown' => '60',
                'preview' => $preview
            ),
            $content
        );

        $this->assertStringContainsString($preview, $output);
        $this->assertStringContainsString('wcl-countdown', $output);
        $this->assertStringNotContainsString($content, $output);
    }
}