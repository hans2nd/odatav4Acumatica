<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\Contracts\SyncStateRepositoryInterface::class,
            \App\Repositories\SyncStateRepository::class
        );

        $this->app->bind(
            \App\Services\Contracts\SyncStateServiceInterface::class,
            \App\Services\SyncStateService::class
        );

        $this->app->bind(
            \App\Services\Queries\Contracts\IncrementalQueryBuilderInterface::class,
            \App\Services\Queries\IncrementalQueryBuilder::class
        );

        $this->app->bind(
            \App\Services\Watermarks\Contracts\CompositeWatermarkInterface::class,
            \App\Services\Watermarks\CompositeWatermarkEngine::class
        );

        $this->app->bind(
            \App\Services\Readers\Contracts\SourceDataReaderInterface::class,
            \App\Services\Readers\SourceDataReader::class
        );

        $this->app->bind(
            \App\Services\Coordinators\Contracts\IncrementalExecutionCoordinatorInterface::class,
            \App\Services\Coordinators\IncrementalExecutionCoordinator::class
        );

        $this->app->bind(
            \App\Services\Validators\Contracts\IncrementalValidatorInterface::class,
            \App\Services\Validators\IncrementalValidator::class
        );

        $this->app->singleton(\App\Services\Mappers\Transformers\TransformerRegistry::class, function ($app) {
            $registry = new \App\Services\Mappers\Transformers\TransformerRegistry();
            $registry->register('date', new \App\Services\Mappers\Transformers\DateTransformer());
            $registry->register('boolean', new \App\Services\Mappers\Transformers\BooleanTransformer());
            $registry->register('integer', new \App\Services\Mappers\Transformers\IntegerTransformer());
            $registry->register('decimal', new \App\Services\Mappers\Transformers\DecimalTransformer());
            $registry->register('string', new \App\Services\Mappers\Transformers\StringTransformer());
            return $registry;
        });

        $this->app->bind(
            \App\Services\Mappers\Contracts\DataMapperInterface::class,
            \App\Services\Mappers\DataMapper::class
        );

        $this->app->singleton(\App\Services\SchemaValidators\ValidationRuleRegistry::class, function ($app) {
            $registry = new \App\Services\SchemaValidators\ValidationRuleRegistry();
            $registry->register('required', new \App\Services\SchemaValidators\Rules\RequiredRule());
            $registry->register('string_length', new \App\Services\SchemaValidators\Rules\StringLengthRule());
            $registry->register('numeric', new \App\Services\SchemaValidators\Rules\NumericTypeRule());
            return $registry;
        });

        $this->app->bind(
            \App\Services\SchemaValidators\Contracts\SchemaValidatorInterface::class,
            \App\Services\SchemaValidators\SchemaValidator::class
        );

        $this->app->bind(
            \App\Services\ChangeDetection\Contracts\FingerprintGeneratorInterface::class,
            \App\Services\ChangeDetection\FingerprintGenerator::class
        );

        $this->app->singleton(\App\Services\ChangeDetection\Strategies\ComparisonStrategyRegistry::class, function ($app) {
            $registry = new \App\Services\ChangeDetection\Strategies\ComparisonStrategyRegistry();
            $registry->register('primary_key', new \App\Services\ChangeDetection\Strategies\PrimaryKeyComparisonStrategy());
            $registry->register('timestamp', new \App\Services\ChangeDetection\Strategies\TimestampComparisonStrategy());
            
            $fingerprintGenerator = $app->make(\App\Services\ChangeDetection\Contracts\FingerprintGeneratorInterface::class);
            $registry->register('fingerprint', new \App\Services\ChangeDetection\Strategies\FingerprintComparisonStrategy($fingerprintGenerator));
            
            return $registry;
        });

        $this->app->bind(
            \App\Services\ChangeDetection\Contracts\ChangeDetectionEngineInterface::class,
            \App\Services\ChangeDetection\ChangeDetectionEngine::class
        );

        $this->app->bind(
            \App\Services\BatchPlanning\Contracts\BatchPlannerInterface::class,
            \App\Services\BatchPlanning\BatchPlanner::class
        );

        $this->app->bind(
            \App\Services\BatchProcessing\Contracts\BatchProcessingEngineInterface::class,
            \App\Services\BatchProcessing\BatchProcessingEngine::class
        );

        $this->app->singleton(\App\Services\ProcessingPipeline\Stages\PipelineStageRegistry::class, function ($app) {
            $registry = new \App\Services\ProcessingPipeline\Stages\PipelineStageRegistry();
            
            // Prioritas 10: Mapper
            $mapper = $app->make(\App\Services\Mappers\Contracts\DataMapperInterface::class);
            $registry->register(new \App\Services\ProcessingPipeline\Stages\MapperStage($mapper), 10);
            
            // Prioritas 20: Validator
            $validator = $app->make(\App\Services\SchemaValidators\Contracts\SchemaValidatorInterface::class);
            $registry->register(new \App\Services\ProcessingPipeline\Stages\ValidatorStage($validator), 20);
            
            // Prioritas 30: Change Detection
            $detector = $app->make(\App\Services\ChangeDetection\Contracts\ChangeDetectionEngineInterface::class);
            $registry->register(new \App\Services\ProcessingPipeline\Stages\ChangeDetectionStage($detector), 30);
            
            // Prioritas 40: Batch Planner
            $planner = $app->make(\App\Services\BatchPlanning\Contracts\BatchPlannerInterface::class);
            $registry->register(new \App\Services\ProcessingPipeline\Stages\BatchPlannerStage($planner), 40);
            
            // Prioritas 50: Batch Execution
            $engine = $app->make(\App\Services\BatchProcessing\Contracts\BatchProcessingEngineInterface::class);
            $registry->register(new \App\Services\ProcessingPipeline\Stages\BatchExecutionStage($engine), 50);
            
            return $registry;
        });

        $this->app->bind(
            \App\Services\ProcessingPipeline\Contracts\ProcessingPipelineInterface::class,
            \App\Services\ProcessingPipeline\ProcessingPipeline::class
        );

        $this->app->singleton(\App\Services\Persistence\PersistenceAdapterRegistry::class, function ($app) {
            $registry = new \App\Services\Persistence\PersistenceAdapterRegistry();
            $registry->register('mysql', new \App\Services\Persistence\Adapters\MysqlAdapter());
            $registry->register('mariadb', new \App\Services\Persistence\Adapters\MariaDbAdapter());
            $registry->register('pgsql', new \App\Services\Persistence\Adapters\PostgresAdapter());
            $registry->register('sqlsrv', new \App\Services\Persistence\Adapters\SqlServerAdapter());
            $registry->register('firebird', new \App\Services\Persistence\Adapters\FirebirdAdapter());
            return $registry;
        });

        $this->app->bind(
            \App\Services\Persistence\PersistenceAdapterResolver::class,
            function ($app) {
                return new \App\Services\Persistence\PersistenceAdapterResolver(
                    $app->make(\App\Services\Persistence\PersistenceAdapterRegistry::class)
                );
            }
        );

        $this->app->bind(
            \App\Services\Persistence\Contracts\ConflictKeyResolverInterface::class,
            \App\Services\Persistence\Resolvers\ConflictKeyResolver::class
        );

        $this->app->bind(
            \App\Services\Persistence\Contracts\UpdateFieldResolverInterface::class,
            \App\Services\Persistence\Resolvers\UpdateFieldResolver::class
        );

        $this->app->bind(
            \App\Services\Persistence\Contracts\UpsertEngineInterface::class,
            \App\Services\Persistence\UpsertEngine::class
        );

        $this->app->singleton(\App\Services\Persistence\DeleteStrategyResolver::class, function ($app) {
            $resolver = new \App\Services\Persistence\DeleteStrategyResolver();
            
            $adapterResolver = $app->make(\App\Services\Persistence\PersistenceAdapterResolver::class);
            
            $resolver->register(\App\Services\Persistence\DeletePolicy::HARD, new \App\Services\Persistence\Strategies\HardDeleteStrategy($adapterResolver));
            $resolver->register(\App\Services\Persistence\DeletePolicy::SOFT, new \App\Services\Persistence\Strategies\SoftDeleteStrategy($adapterResolver));
            $resolver->register(\App\Services\Persistence\DeletePolicy::ARCHIVE, new \App\Services\Persistence\Strategies\ArchiveDeleteStrategy($adapterResolver));
            $resolver->register(\App\Services\Persistence\DeletePolicy::IGNORE, new \App\Services\Persistence\Strategies\IgnoreDeleteStrategy());
            
            return $resolver;
        });

        $this->app->singleton(\App\Services\Persistence\Strategies\Conflicts\ConflictResolutionStrategyRegistry::class, function ($app) {
            $registry = new \App\Services\Persistence\Strategies\Conflicts\ConflictResolutionStrategyRegistry();
            
            $registry->register(\App\Services\Persistence\ConflictResolutionPolicy::LAST_WRITE_WINS, new \App\Services\Persistence\Strategies\Conflicts\LastWriteWinsStrategy());
            $registry->register(\App\Services\Persistence\ConflictResolutionPolicy::FIRST_WRITE_WINS, new \App\Services\Persistence\Strategies\Conflicts\FirstWriteWinsStrategy());
            $registry->register(\App\Services\Persistence\ConflictResolutionPolicy::SOURCE_PRIORITY, new \App\Services\Persistence\Strategies\Conflicts\LastWriteWinsStrategy()); // Alias Last Write
            $registry->register(\App\Services\Persistence\ConflictResolutionPolicy::DESTINATION_PRIORITY, new \App\Services\Persistence\Strategies\Conflicts\FirstWriteWinsStrategy()); // Alias First Write
            $registry->register(\App\Services\Persistence\ConflictResolutionPolicy::TIMESTAMP_COMPARISON, new \App\Services\Persistence\Strategies\Conflicts\TimestampComparisonStrategy());
            $registry->register(\App\Services\Persistence\ConflictResolutionPolicy::VERSION_COMPARISON, new \App\Services\Persistence\Strategies\Conflicts\VersionComparisonStrategy());
            $registry->register(\App\Services\Persistence\ConflictResolutionPolicy::SKIP_CONFLICT, new \App\Services\Persistence\Strategies\Conflicts\SkipConflictStrategy());
            $registry->register(\App\Services\Persistence\ConflictResolutionPolicy::FAIL_FAST, new \App\Services\Persistence\Strategies\Conflicts\FailFastStrategy());
            
            return $registry;
        });

        $this->app->bind(
            \App\Services\Persistence\Contracts\ConflictResolutionEngineInterface::class,
            \App\Services\Persistence\ConflictResolutionEngine::class
        );

        $this->app->bind(
            \App\Services\Persistence\Contracts\SavepointManagerInterface::class,
            \App\Services\Persistence\SavepointManager::class
        );

        $this->app->singleton(\App\Services\Persistence\Contracts\TransactionManagerInterface::class, function ($app) {
            return new \App\Services\Persistence\TransactionManager(
                $app->make(\App\Services\Persistence\PersistenceAdapterResolver::class),
                $app->make(\App\Services\Persistence\Contracts\SavepointManagerInterface::class)
            );
        });

        // Pipeline Stages Bindings
        $this->app->bind(\App\Services\Persistence\Pipeline\Stages\TransactionStage::class);
        $this->app->bind(\App\Services\Persistence\Pipeline\Stages\ConflictResolutionStage::class);
        $this->app->bind(\App\Services\Persistence\Pipeline\Stages\DeleteStage::class);
        $this->app->bind(\App\Services\Persistence\Pipeline\Stages\UpsertStage::class);

        $this->app->singleton(\App\Services\Persistence\Pipeline\PersistenceStageRegistry::class, function ($app) {
            $registry = new \App\Services\Persistence\Pipeline\PersistenceStageRegistry();
            
            // Urutan Pipa: Transaksi Membungkus -> Konflik -> Hapus -> Sisip
            $registry->addStage($app->make(\App\Services\Persistence\Pipeline\Stages\TransactionStage::class));
            $registry->addStage($app->make(\App\Services\Persistence\Pipeline\Stages\ConflictResolutionStage::class));
            $registry->addStage($app->make(\App\Services\Persistence\Pipeline\Stages\DeleteStage::class));
            $registry->addStage($app->make(\App\Services\Persistence\Pipeline\Stages\UpsertStage::class));

            return $registry;
        });

        $this->app->bind(
            \App\Services\Persistence\Contracts\PersistencePipelineInterface::class,
            \App\Services\Persistence\Pipeline\PersistencePipeline::class
        );

        // ==========================================
        // RELIABILITY PHASE
        // ==========================================
        $this->app->bind(
            \App\Services\Reliability\Contracts\RetryEligibilityEvaluatorInterface::class,
            \App\Services\Reliability\Evaluators\DefaultRetryEligibilityEvaluator::class
        );

        $this->app->singleton(\App\Services\Reliability\Policies\RetryPolicyRegistry::class, function ($app) {
            $registry = new \App\Services\Reliability\Policies\RetryPolicyRegistry();
            $registry->register(\App\Services\Reliability\RetryPolicy::NO_RETRY, new \App\Services\Reliability\Policies\NoRetryPolicy());
            $registry->register(\App\Services\Reliability\RetryPolicy::FIXED, new \App\Services\Reliability\Policies\FixedRetryPolicy());
            $registry->register(\App\Services\Reliability\RetryPolicy::LINEAR_BACKOFF, new \App\Services\Reliability\Policies\LinearBackoffPolicy());
            $registry->register(\App\Services\Reliability\RetryPolicy::EXPONENTIAL_BACKOFF, new \App\Services\Reliability\Policies\ExponentialBackoffPolicy());
            $registry->register(\App\Services\Reliability\RetryPolicy::EXPONENTIAL_BACKOFF_WITH_JITTER, new \App\Services\Reliability\Policies\ExponentialBackoffWithJitterPolicy());
            $registry->register(\App\Services\Reliability\RetryPolicy::INFINITE, new \App\Services\Reliability\Policies\InfiniteRetryPolicy());
            return $registry;
        });

        $this->app->bind(
            \App\Services\Reliability\Contracts\RetryPolicyEngineInterface::class,
            \App\Services\Reliability\RetryPolicyEngine::class
        );

        // Failure Classification Bindings
        $this->app->singleton(\App\Services\Reliability\Classification\Classifiers\FailureClassifierRegistry::class, function ($app) {
            $registry = new \App\Services\Reliability\Classification\Classifiers\FailureClassifierRegistry();
            // Classifier dimasukkan berurutan, dari yang paling spesifik ke yang paling generik
            $registry->addClassifier(new \App\Services\Reliability\Classification\Classifiers\DefaultExceptionClassifier());
            return $registry;
        });

        $this->app->bind(
            \App\Services\Reliability\Contracts\FailureClassificationEngineInterface::class,
            \App\Services\Reliability\Classification\FailureClassificationEngine::class
        );

        // Resume & Checkpoint Recovery Bindings
        $this->app->singleton(
            \App\Services\Reliability\Contracts\CheckpointProviderInterface::class,
            \App\Services\Reliability\Recovery\Providers\InMemoryCheckpointProvider::class
        );

        $this->app->bind(
            \App\Services\Reliability\Contracts\CheckpointRecoveryInterface::class,
            \App\Services\Reliability\Recovery\CheckpointRecovery::class
        );

        $this->app->bind(
            \App\Services\Reliability\Contracts\ResumeEngineInterface::class,
            \App\Services\Reliability\Recovery\ResumeEngine::class
        );

        // Reliability Pipeline Bindings
        $this->app->bind(\App\Services\Reliability\Pipeline\Stages\FailureClassificationStage::class);
        $this->app->bind(\App\Services\Reliability\Pipeline\Stages\RetryPolicyStage::class);
        $this->app->bind(\App\Services\Reliability\Pipeline\Stages\ResumeRecoveryStage::class);

        $this->app->singleton(\App\Services\Reliability\Pipeline\ReliabilityStageRegistry::class, function ($app) {
            $registry = new \App\Services\Reliability\Pipeline\ReliabilityStageRegistry();
            
            // Urutan Pipa Keandalan: Klasifikasi -> Retry -> Resume
            $registry->addStage($app->make(\App\Services\Reliability\Pipeline\Stages\FailureClassificationStage::class));
            $registry->addStage($app->make(\App\Services\Reliability\Pipeline\Stages\RetryPolicyStage::class));
            $registry->addStage($app->make(\App\Services\Reliability\Pipeline\Stages\ResumeRecoveryStage::class));

            return $registry;
        });

        $this->app->bind(
            \App\Services\Reliability\Contracts\ReliabilityPipelineInterface::class,
            \App\Services\Reliability\Pipeline\ReliabilityPipeline::class
        );

        // Synchronization Orchestrator Bindings
        $this->app->bind(\App\Services\Orchestration\Stages\IncrementalCoordinationStage::class);
        $this->app->bind(\App\Services\Orchestration\Stages\ProcessingPipelineStage::class);
        $this->app->bind(\App\Services\Orchestration\Stages\PersistencePipelineStage::class);
        $this->app->bind(\App\Services\Orchestration\Stages\ReliabilityPipelineStage::class);
        $this->app->bind(\App\Services\Orchestration\Stages\SynchronizationSummaryStage::class);

        $this->app->singleton(\App\Services\Orchestration\SynchronizationStageRegistry::class, function ($app) {
            $registry = new \App\Services\Orchestration\SynchronizationStageRegistry();
            
            // Urutan Orkestrasi End-to-End
            $registry->addStage($app->make(\App\Services\Orchestration\Stages\IncrementalCoordinationStage::class));
            $registry->addStage($app->make(\App\Services\Orchestration\Stages\ProcessingPipelineStage::class));
            $registry->addStage($app->make(\App\Services\Orchestration\Stages\PersistencePipelineStage::class));
            $registry->addStage($app->make(\App\Services\Orchestration\Stages\ReliabilityPipelineStage::class));
            $registry->addStage($app->make(\App\Services\Orchestration\Stages\SynchronizationSummaryStage::class));

            return $registry;
        });

        $this->app->bind(
            \App\Services\Orchestration\Contracts\SynchronizationOrchestratorInterface::class,
            \App\Services\Orchestration\SynchronizationOrchestrator::class
        );

        // Scheduler Engine Bindings
        $this->app->singleton(\App\Services\Scheduling\ScheduleRegistry::class, function ($app) {
            $registry = new \App\Services\Scheduling\ScheduleRegistry();
            
            $registry->registerPolicy(new \App\Services\Scheduling\Policies\CronSchedulePolicy());
            $registry->registerPolicy(new \App\Services\Scheduling\Policies\FixedIntervalSchedulePolicy());
            $registry->registerPolicy(new \App\Services\Scheduling\Policies\ManualTriggerPolicy());

            return $registry;
        });

        $this->app->bind(
            \App\Services\Scheduling\Contracts\SchedulerEngineInterface::class,
            \App\Services\Scheduling\SchedulerEngine::class
        );

        // Observability Foundation Bindings
        $this->app->singleton(\App\Services\Observability\ObservabilityRegistry::class, function ($app) {
            $registry = new \App\Services\Observability\ObservabilityRegistry();
            
            // Registrasi provider bawaan (Ke Log)
            $registry->registerProvider(new \App\Services\Observability\Providers\LogObservabilityProvider());

            return $registry;
        });

        // Engine menggunakan Singleton agar jejak traces bertahan dalam satu siklus request (in-memory tracing)
        $this->app->singleton(
            \App\Services\Observability\Contracts\ObservabilityEngineInterface::class,
            \App\Services\Observability\ObservabilityEngine::class
        );

        // Monitoring Engine Bindings
        $this->app->singleton(\App\Services\Monitoring\MonitoringRegistry::class, function ($app) {
            $registry = new \App\Services\Monitoring\MonitoringRegistry();
            
            // Daftarkan aturan diagnostik bawaan
            $registry->registerRule(new \App\Services\Monitoring\Rules\RepeatedFailureRule());
            $registry->registerRule(new \App\Services\Monitoring\Rules\StalledExecutionRule());

            return $registry;
        });

        // Monitoring Engine Bindings
        $this->app->singleton(\App\Services\Monitoring\MonitoringRegistry::class, function ($app) {
            $registry = new \App\Services\Monitoring\MonitoringRegistry();
            
            // Daftarkan aturan diagnostik bawaan
            $registry->registerRule(new \App\Services\Monitoring\Rules\RepeatedFailureRule());
            $registry->registerRule(new \App\Services\Monitoring\Rules\StalledExecutionRule());

            return $registry;
        });

        $this->app->bind(
            \App\Services\Monitoring\Contracts\MonitoringEngineInterface::class,
            \App\Services\Monitoring\MonitoringEngine::class
        );

        // Metrics & Telemetry Engine Bindings
        $this->app->singleton(\App\Services\Telemetry\MetricRegistry::class, function ($app) {
            $registry = new \App\Services\Telemetry\MetricRegistry();
            
            // Registrasi Exporter bawaan (Null/Hampa)
            $registry->registerExporter(new \App\Services\Telemetry\Exporters\NullExporter());

            // Contoh registrasi definisi metrik dasar
            $registry->registerDefinition(new \App\Services\Telemetry\Models\MetricDefinition('execution_duration', 'histogram', 'Durasi eksekusi sinkronisasi'));
            $registry->registerDefinition(new \App\Services\Telemetry\Models\MetricDefinition('rows_processed', 'counter', 'Total baris data yang diproses'));

            return $registry;
        });

        $this->app->bind(
            \App\Services\Telemetry\Contracts\MetricAggregatorInterface::class,
            \App\Services\Telemetry\Aggregators\StandardAggregator::class
        );

        $this->app->bind(
            \App\Services\Telemetry\Contracts\MetricsEngineInterface::class,
            \App\Services\Telemetry\MetricsEngine::class
        );

        // Alerting & Notification Engine Bindings
        $this->app->singleton(\App\Services\Alerting\AlertRegistry::class, function ($app) {
            $registry = new \App\Services\Alerting\AlertRegistry();
            
            // Registrasi aturan deteksi
            $registry->registerRule(new \App\Services\Alerting\Rules\ExecutionFailureRule());
            $registry->registerRule(new \App\Services\Alerting\Rules\MemoryThresholdRule());

            // Registrasi penyedia notifikasi (Log Fallback)
            $registry->registerProvider(new \App\Services\Alerting\Providers\LogNotificationProvider());

            return $registry;
        });

        $this->app->bind(
            \App\Services\Alerting\Contracts\AlertDispatcherInterface::class,
            \App\Services\Alerting\Dispatchers\StandardAlertDispatcher::class
        );

        $this->app->bind(
            \App\Services\Alerting\Contracts\AlertingEngineInterface::class,
            \App\Services\Alerting\AlertingEngine::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            // Only proceed if the connections table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('connections')) {
                return;
            }

            // Register Firebird Database Resolver
            \Illuminate\Database\Connection::resolverFor('firebird', function ($connection, $database, $prefix, $config) {
                $host = $config['host'] ?? '127.0.0.1';
                $port = $config['port'] ?? '3051';
                $db = $config['database'] ?? '';
                $dsn = "firebird:dbname={$host}/{$port}:{$db}";
                $pdo = new \PDO($dsn, $config['username'] ?? '', $config['password'] ?? '');
                return new \Illuminate\Database\Connection($pdo, $database, $prefix, $config);
            });

            $connectionService = app(\App\Services\ConnectionService::class);
            $connections = $connectionService->getAll();

            foreach ($connections as $key => $conn) {
                if (in_array($conn['type'], \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes())) {
                    $creds = $conn['credentials'] ?? [];

                    if ($conn['type'] === 'local_env' || $conn['type'] === 'local') {
                        $defaultConn = config('database.default');
                        config(["database.connections.{$key}" => config("database.connections.{$defaultConn}")]);
                    } elseif ($conn['type'] === 'mysql') {
                        config([
                            "database.connections.{$key}" => [
                                'driver' => 'mysql',
                                'url' => env('DATABASE_URL'),
                                'host' => $creds['host'] ?? '127.0.0.1',
                                'port' => $creds['port'] ?? '3306',
                                'database' => $creds['database'] ?? '',
                                'username' => $creds['username'] ?? '',
                                'password' => $creds['password'] ?? '',
                                'unix_socket' => env('DB_SOCKET', ''),
                                'charset' => 'utf8mb4',
                                'collation' => 'utf8mb4_unicode_ci',
                                'prefix' => '',
                                'prefix_indexes' => true,
                                'strict' => true,
                                'engine' => null,
                                'options' => extension_loaded('pdo_mysql') ? array_filter([
                                    \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                                ]) : [],
                            ]
                        ]);
                    } elseif ($conn['type'] === 'sqlsrv') {
                        config([
                            "database.connections.{$key}" => [
                                'driver' => 'sqlsrv',
                                'url' => env('DATABASE_URL'),
                                'host' => $creds['host'] ?? 'localhost',
                                'port' => $creds['port'] ?? '1433',
                                'database' => $creds['database'] ?? '',
                                'username' => $creds['username'] ?? '',
                                'password' => $creds['password'] ?? '',
                                'charset' => 'utf8',
                                'prefix' => '',
                                'prefix_indexes' => true,
                                // 'encrypt' => env('DB_ENCRYPT', 'yes'),
                                // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
                            ]
                        ]);
                    } elseif ($conn['type'] === 'firebird') {
                        config([
                            "database.connections.{$key}" => [
                                'driver' => 'firebird',
                                'host' => $creds['host'] ?? '127.0.0.1',
                                'port' => $creds['port'] ?? '3051',
                                'database' => $creds['database'] ?? '',
                                'username' => $creds['username'] ?? '',
                                'password' => $creds['password'] ?? '',
                                'charset' => 'utf8',
                                'prefix' => '',
                            ]
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore during initial setup or if table doesn't exist yet
        }
    }
}
