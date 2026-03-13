<?php
/**
 * Integration test placeholder for capability sync.
 *
 * @package MediaCategories
 */

class Test_Capabilities extends WP_UnitTestCase {
	/**
	 * Administrators should retain management access.
	 *
	 * @return void
	 */
	public function test_administrator_role_exists() {
		$this->assertNotNull( get_role( 'administrator' ) );
	}
}
