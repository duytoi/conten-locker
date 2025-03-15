<?php
class Test_Protection_Service extends WP_UnitTestCase {
    private $protection_service;

    public function setUp() {
        parent::setUp();
        $this->protection_service = new WCL_Protection_Service();
    }

    public function test_create_protection() {
        $protection_data = array(
            'type' => 'password',
            'settings' => array(
                'password' => 'test123'
            )
        );

        $protection_id = $this->protection_service->create_protection($protection_data);
        $this->assertNotFalse($protection_id);

        $protection = $this->protection_service->get_protection($protection_id);
        $this->assertEquals($protection_data['type'], $protection->type);
        $this->assertEquals($protection_data['settings'], $protection->settings);
    }

    public function test_verify_password_protection() {
        $protection_id = $this->protection_service->create_protection(array(
            'type' => 'password',
            'settings' => array(
                'password' => 'test123'
            )
        ));

        // Test correct password
        $this->assertTrue(
            $this->protection_service->verify_password($protection_id, 'test123')
        );

        // Test incorrect password
        $this->assertFalse(
            $this->protection_service->verify_password($protection_id, 'wrong')
        );
    }

    public function test_unlock_protection() {
        $protection_id = $this->protection_service->create_protection(array(
            'type' => 'password',
            'settings' => array(
                'password' => 'test123'
            )
        ));

        // Unlock protection
        $this->protection_service->unlock_protection($protection_id);

        // Verify it's unlocked
        $this->assertTrue($this->protection_service->is_unlocked($protection_id));
    }

    public function test_protection_expiry() {
        $protection_id = $this->protection_service->create_protection(array(
            'type' => 'countdown',
            'settings' => array(
                'duration' => 1 // 1 second
            )
        ));

        // Unlock protection
        $this->protection_service->unlock_protection($protection_id);
        
        // Verify it's unlocked
        $this->assertTrue($this->protection_service->is_unlocked($protection_id));

        // Wait for expiry
        sleep(2);

        // Verify it's locked again
        $this->assertFalse($this->protection_service->is_unlocked($protection_id));
    }
}