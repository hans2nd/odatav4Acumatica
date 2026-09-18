<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Connection;

class EncryptConnectionCredentials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'connections:encrypt-credentials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypts all existing plaintext connection credentials.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting credentials encryption migration...');
        
        $connections = Connection::all();
        $migrated = 0;
        
        foreach ($connections as $connection) {
            // The custom cast EncryptedCredentials handles reading the old plaintext JSON.
            // By retrieving it, we get the array.
            $credentials = $connection->credentials;
            
            if (!empty($credentials)) {
                // By assigning it back and saving, the custom cast will now encrypt it.
                // But Eloquent only saves if it detects a change.
                // We can force a save by updating the credentials field.
                $connection->credentials = $credentials;
                
                // Force Eloquent to think it's dirty since it's an array and might not track properly
                $connection->syncOriginal();
                $connection->setAttribute('credentials', $credentials);
                
                // Alternatively, we can use DB update directly with the encrypted value
                // but utilizing the model is safer for events and mutators.
                $encrypted = \Illuminate\Support\Facades\Crypt::encryptString(json_encode($credentials));
                \Illuminate\Support\Facades\DB::table('connections')
                    ->where('id', $connection->id)
                    ->update(['credentials' => $encrypted]);
                    
                $migrated++;
            }
        }
        
        $this->info("Successfully encrypted credentials for {$migrated} connections.");
        return 0;
    }
}
