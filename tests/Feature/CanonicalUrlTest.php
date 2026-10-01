<?php

namespace Tests\Feature;

use Tests\TestCase;

class CanonicalUrlTest extends TestCase
{
    private function production(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://ims.civic-tobacco-machinery.com']);
    }

    public function test_main_domain_subfolder_redirects_to_subdomain(): void
    {
        $this->production();
        $this->get('http://civic-tobacco-machinery.com/ims')->assertRedirect('https://ims.civic-tobacco-machinery.com/')->assertStatus(301);
        $this->get('http://civic-tobacco-machinery.com/ims/login')->assertRedirect('https://ims.civic-tobacco-machinery.com/login');
        $this->get('https://www.civic-tobacco-machinery.com/ims/cnc/dashboard?period=year')->assertRedirect('https://ims.civic-tobacco-machinery.com/cnc/dashboard?period=year');
    }

    public function test_other_alias_keeps_path_and_canonical_host_is_untouched(): void
    {
        $this->production();
        $this->get('http://ims.civic-tobacco-machinery.com.example.net/login')->assertRedirect('https://ims.civic-tobacco-machinery.com/login');
        $this->get('https://ims.civic-tobacco-machinery.com/login')->assertStatus(302); // normal app behaviour (→ /setup, no users yet)
    }

    public function test_inactive_outside_production(): void
    {
        config(['app.url' => 'https://ims.civic-tobacco-machinery.com']);
        $this->get('http://civic-tobacco-machinery.com/login')->assertStatus(302)->assertRedirect(route('setup'));
    }
}
