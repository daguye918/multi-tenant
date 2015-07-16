<?php namespace HynMe\MultiTenant\Tests;


use File, DB;
use HynMe\Framework\Testing\TestCase;
use HynMe\MultiTenant\MultiTenantServiceProvider;

class TenancySetupTest extends TestCase
{
    /**
     * @beforeClass
     */
    public function setUpRepositories()
    {
        /** @var \HynMe\MultiTenant\Contracts\TenantRepositoryContract tenant */
        $this->tenant = $this->app->make('HynMe\MultiTenant\Contracts\TenantRepositoryContract');
        /** @var \HynMe\MultiTenant\Contracts\HostnameRepositoryContract hostname */
        $this->hostname = $this->app->make('HynMe\MultiTenant\Contracts\HostnameRepositoryContract');
        /** @var \HynMe\MultiTenant\Contracts\WebsiteRepositoryContract hostname */
        $this->website = $this->app->make('HynMe\MultiTenant\Contracts\WebsiteRepositoryContract');
    }

    public function testPackages()
    {
        $this->assertTrue(class_exists('HynMe\Framework\FrameworkServiceProvider'), 'Class FrameworkServiceProvider does not exist');
        $this->assertNotFalse($this->app->make('hyn.package.multi-tenant'), 'packages are not loaded through FrameworkServiceProvider');

        $this->assertTrue(in_array(MultiTenantServiceProvider::class, $this->app->getLoadedProviders()), 'MultiTenantService provider is not loaded in Laravel');
        $this->assertTrue($this->app->isBooted());

        $this->assertNotFalse($this->app->make('hyn.package.multi-tenant'));
    }

    /**
     * @depends testPackages
     */
    public function testCommand()
    {
        // create first tenant
        $this->assertEquals(0, $this->artisan('multi-tenant:setup', [
            '--tenant' => 'example',
            '--hostname' => 'example.org',
            '--email' => 'info@example.org',
            '--webserver' => 'no'
        ]));


    }

    /**
     * @after testCommand
     */
    public function setUpExampleTenant()
    {
        $this->exampleTenant = $this->tenant->findByName('example');
        $this->exampleHostname = $this->hostname->findByHostname('example.org');
        $this->exampleWebsite = $this->website->findByHostname('example.org');
    }

    /**
     * @depends testCommand
     */
    public function testTenantExistence()
    {
        $this->assertNotNull($this->exampleTenant, 'Tenant from command has not been created');
    }

    /**
     * @depends testTenantExistence
     */
    public function testHostnameExistence()
    {
        $this->assertNotNull($this->exampleHostname, 'Hostname from command has not been created');
    }

    /**
     * @depends testTenantExistence
     */
    public function testDatabaseExists()
    {
        $databases = DB::connection('hyn')->select('SHOW DATABASES');

        $found = false;
        $list = [];

        foreach($databases as $database)
        {
            if(substr($database->Database,0,1) == 1)
                $found = true;

            $list[] = $database->Database;
        }

        $this->assertTrue($found, "Databases found: " . implode(', ', $list));
    }

    /**
     * @depends testDatabaseExists
     */
    public function testTenantMigrationRuns()
    {
        $this->assertEquals(0, $this->artisan('migrate', [
            '--tenant' => 'true',
            '--path' => __DIR__ . '/database/migrations/'
        ]));
    }


    /**
     * @depends testTenantMigrationRuns
     */
    public function testTenantMigratedTableExists()
    {
        /** @var \HynMe\MultiTenant\Models\Hostname|null $website */
        $hostname = $this->exampleHostname;

        $this->assertGreaterThan(0, $hostname
            ->website
            ->database
            ->get()
            ->table('tenant_migration_test')
            ->insertGetId(['some_field' => 'foo'])
        );
    }

    /**
     * @depends testTenantMigrationRuns
     */
    public function testTenantMigrationEntryExists()
    {
        /** @var \HynMe\MultiTenant\Models\Hostname|null $website */
        $hostname = $this->exampleHostname;

        if(!$hostname)
            throw new \Exception("Unit test hostname not found");

        $hostname->website->database->setCurrent();

        foreach(File::allFiles(__DIR__ . '/database/migrations') as $file)
        {
            $fileBaseName = $file->getBaseName('.'.$file->getExtension());
            $this->seeInDatabase('migrations', ['migration' => $fileBaseName], $hostname->website->database->name);
        }
    }
}