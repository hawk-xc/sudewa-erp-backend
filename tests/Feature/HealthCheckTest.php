<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * Test the public health check endpoint on web.
     */
    public function test_web_health_check_endpoint_is_accessible()
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'app' => [
                         'name',
                         'environment',
                         'debug_mode',
                         'timezone',
                         'laravel_version',
                         'php_version',
                         'timestamp',
                     ],
                     'checks' => [
                         'database' => [
                             'status',
                             'connection',
                             'database_name',
                             'latency_ms',
                         ],
                         'cache' => [
                             'status',
                             'driver',
                         ],
                         'storage' => [
                             'status',
                             'paths' => [
                                 'app' => [
                                     'path',
                                     'writable',
                                 ],
                                 'framework' => [
                                     'path',
                                     'writable',
                                 ],
                                 'logs' => [
                                     'path',
                                     'writable',
                                 ],
                             ],
                         ],
                         'system' => [
                             'disk' => [
                                 'free',
                                 'total',
                                 'used',
                                 'percentage_used',
                             ],
                             'memory_limit',
                             'php_memory_usage',
                         ],
                     ],
                 ]);

        $this->assertEquals('healthy', $response->json('status'));
    }

    /**
     * Test the health check endpoint on API.
     */
    public function test_api_health_check_endpoint_is_accessible()
    {
        $response = $this->getJson('/wapi/health');

        $response->assertStatus(200)
                 ->assertJsonFragment(['status' => 'healthy']);
    }
}
