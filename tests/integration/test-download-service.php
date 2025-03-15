<?php
class Test_Download_Service extends WP_UnitTestCase {
    private $download_service;
    private $test_file;

    public function setUp() {
        parent::setUp();
        $this->download_service = new WCL_Download_Service();
        
        // Create test file
        $this->test_file = wp_upload_dir()['path'] . '/test-file.txt';
        file_put_contents($this->test_file, 'Test content');
    }

    public function tearDown() {
        // Cleanup test file
        if (file_exists($this->test_file)) {
            unlink($this->test_file);
        }
        parent::tearDown();
    }

    public function test_create_download() {
        $download_data = array(
            'title' => 'Test Download',
            'description' => 'Test Description',
            'status' => 'active'
        );

        $download_id = $this->download_service->create_download(
            $download_data,
            array(
                'tmp_name' => $this->test_file,
                'name' => 'test-file.txt'
            )
        );

        $this->assertNotFalse($download_id);
        
        $download = $this->download_service->get_download($download_id);
        $this->assertEquals($download_data['title'], $download->title);
        $this->assertEquals($download_data['description'], $download->description);
        $this->assertEquals($download_data['status'], $download->status);
        $this->assertFileExists($download->file_path);
    }

    public function test_update_download() {
        // First create a download
        $download_id = $this->download_service->create_download(
            array(
                'title' => 'Original Title',
                'status' => 'active'
            ),
            array(
                'tmp_name' => $this->test_file,
                'name' => 'test-file.txt'
            )
        );

        // Update the download
        $update_data = array(
            'title' => 'Updated Title',
            'description' => 'Updated Description'
        );

        $result = $this->download_service->update_download($download_id, $update_data);
        $this->assertTrue($result);

        $updated_download = $this->download_service->get_download($download_id);
        $this->assertEquals($update_data['title'], $updated_download->title);
        $this->assertEquals($update_data['description'], $updated_download->description);
    }

    public function test_delete_download() {
        // Create a download
        $download_id = $this->download_service->create_download(
            array(
                'title' => 'Test Download',
                'status' => 'active'
            ),
            array(
                'tmp_name' => $this->test_file,
                'name' => 'test-file.txt'
            )
        );

        $download = $this->download_service->get_download($download_id);
        $file_path = $download->file_path;

        // Delete the download
        $result = $this->download_service->delete_download($download_id);
        $this->assertTrue($result);

        // Verify download is deleted
        $this->assertNull($this->download_service->get_download($download_id));
        $this->assertFileNotExists($file_path);
    }

    public function test_get_download_url() {
        $download_id = $this->download_service->create_download(
            array(
                'title' => 'Test Download',
                'status' => 'active'
            ),
            array(
                'tmp_name' => $this->test_file,
                'name' => 'test-file.txt'
            )
        );

        $url = $this->download_service->get_download_url($download_id);
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('download=', $url);
        $this->assertStringContainsString('nonce=', $url);
    }
}