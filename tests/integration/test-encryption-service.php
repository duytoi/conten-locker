<?php
class Test_Encryption_Service extends WP_UnitTestCase {
    private $encryption_service;
    private $test_file;
    private $test_content = 'This is test content for encryption';

    public function setUp() {
        parent::setUp();
        $this->encryption_service = new WCL_Encryption_Service();
        
        // Create test file
        $this->test_file = wp_upload_dir()['path'] . '/test-encryption.txt';
        file_put_contents($this->test_file, $this->test_content);
    }

    public function tearDown() {
        // Cleanup test files
        if (file_exists($this->test_file)) {
            unlink($this->test_file);
        }
        if (file_exists($this->test_file . '.encrypted')) {
            unlink($this->test_file . '.encrypted');
        }
        parent::tearDown();
    }

    public function test_encrypt_decrypt_file() {
        // Encrypt file
        $encrypted_file = $this->encryption_service->encrypt_file($this->test_file);
        $this->assertFileExists($encrypted_file);
        $this->assertNotEquals(
            file_get_contents($this->test_file),
            file_get_contents($encrypted_file)
        );

        // Decrypt file
        $decrypted_file = $this->encryption_service->decrypt_file($encrypted_file);
        $this->assertFileExists($decrypted_file);
        $this->assertEquals(
            $this->test_content,
            file_get_contents($decrypted_file)
        );
    }

    public function test_verify_file_integrity() {
        // Encrypt file
        $encrypted_file = $this->encryption_service->encrypt_file($this->test_file);
        
        // Verify integrity
        $this->assertTrue($this->encryption_service->verify_file_integrity($encrypted_file));

        // Corrupt file
        file_put_contents($encrypted_file, 'corrupted data');
        $this->assertFalse($this->encryption_service->verify_file_integrity($encrypted_file));
    }

    public function test_get_file_info() {
        // Encrypt file
        $encrypted_file = $this->encryption_service->encrypt_file($this->test_file);
        
        $info = $this->encryption_service->get_file_info($encrypted_file);
        $this->assertArrayHasKey('size', $info);
        $this->assertArrayHasKey('encrypted_at', $info);
        $this->assertTrue($info['is_encrypted']);
    }
}