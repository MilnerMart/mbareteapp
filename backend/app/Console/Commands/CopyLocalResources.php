<?php

namespace App\Console\Commands;

use App\Core\EntityStatus;
use App\Db\DbConnector;
use App\Models\Resource;
use App\PublicException;
use App\ServiceApi\ProviderApiClient;
use App\ServiceApi\ResourceApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CopyLocalResources extends Command
{
    protected $signature = 'resources:copy-local {providerConfigId : Credencial del proveedor de destino} {--dry-run : Solo lista lo que se copiaria}';

    protected $description = 'Copia a otro proveedor de almacenamiento (ej. S3) las imagenes guardadas en public/, manteniendo sus claves';

    public function handle(DbConnector $dbConnector): int
    {
        try {
            $client = ProviderApiClient::allocForConfigId($dbConnector, (int)$this->argument('providerConfigId'));
        } catch (PublicException $exception) {
            $this->error($exception->getText());
            return self::FAILURE;
        }
        if(!$client instanceof ResourceApi){
            $this->error($client->getProvider()->getName().' no es un proveedor de almacenamiento de recursos');
            return self::FAILURE;
        }

        $copied = $missing = $failed = 0;
        foreach ($this->queryLocalKeyList($dbConnector) as $resourceKey) {
            $localPath = public_path($resourceKey);
            if(!File::isFile($localPath)){
                $missing++;
                $this->line('<comment>sin archivo</comment> '.$resourceKey);
                continue;
            }
            if($this->option('dry-run')){
                $copied++;
                $this->line('copiaria '.$resourceKey);
                continue;
            }
            try {
                $client->uploadResource($localPath, $resourceKey);
                $copied++;
                $this->line('<info>copiado</info> '.$resourceKey);
            } catch (PublicException $exception) {
                $failed++;
                $this->line('<error>error</error> '.$resourceKey.': '.$exception->getText());
            }
        }

        $this->newLine();
        $this->info(($this->option('dry-run') ? 'A copiar: ' : 'Copiados: ').$copied.' · Sin archivo local: '.$missing.' · Con error: '.$failed);
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function queryLocalKeyList(DbConnector $dbConnector): array
    {
        $resourceKeyList = Resource::allocDbTable($dbConnector)
            ->where('status', '!=', EntityStatus::statusIdDeleted)
            ->where('url', 'not like', Resource::publicKeyPrefix.'%')
            ->where('url', 'not like', 'http%')
            ->pluck('url')->all();
        $profileKeyList = DB::table('users')->whereNotNull('profile_image_url')
            ->where('profile_image_url', 'not like', 'http%')
            ->pluck('profile_image_url')->all();
        return array_values(array_unique(array_merge($resourceKeyList, $profileKeyList)));
    }
}
