<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceTask;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SampleSystemDataSeeder extends Seeder
{
    private const SAMPLE_MARKER = '[SAMPLE DATA]';

    public function run(): void
    {
        $actors = $this->getActors();
        $catalog = $this->assetCatalog();
        $cases = $this->maintenanceCases();
        $this->assertNoRealRecordCollisions($cases);

        DB::transaction(function () use ($actors, $catalog, $cases): void {
            $categories = [];
            foreach (array_column($catalog, 'category') as $name) {
                $categories[$name] = Category::firstOrCreate(['name' => $name]);
            }

            $locations = [];
            foreach ($this->locations() as $name) {
                $locations[$name] = Location::firstOrCreate(['name' => $name]);
            }

            $reportedCases = [];
            foreach ($cases as $caseIndex => $case) {
                $reportedCases[$case['asset_index']] = $caseIndex;
            }

            $assets = [];
            for ($index = 0; $index < 200; $index++) {
                $categoryIndex = intdiv($index, 20);
                $category = $catalog[$categoryIndex];
                $item = $category['items'][$index % 10];
                $propertyNumber = sprintf('DEMO-LGU-2026-%04d', $index + 1);
                $locationNames = array_keys($locations);
                $locationName = $locationNames[($index * 7 + $categoryIndex) % count($locationNames)];
                [$condition, $status] = $this->assetState($index, $reportedCases, $cases);
                $cost = round($item['cost'] * (0.95 + (($index % 10) * 0.012)), 2);

                $asset = Asset::withTrashed()->firstOrCreate(
                    ['asset_tag' => $propertyNumber],
                    [
                        'asset_name' => $item['name'],
                        'category_id' => $categories[$category['category']]->id,
                        'location_id' => $locations[$locationName]->id,
                        'brand' => $item['brand'],
                        'model' => $item['model'],
                        'serial_number' => $item['serial']
                            ? sprintf('LGU-%s-%04d', $category['code'], $index + 1)
                            : null,
                        'description' => sprintf(
                            '%s acquired for %s use in %s.',
                            $item['description'],
                            strtolower($category['category']),
                            $locationName,
                        ),
                        'room_area' => $this->rooms()[$index % count($this->rooms())],
                        'purchase_date' => now()->subDays(120 + (($index * 31) % 2200))->toDateString(),
                        'purchase_cost' => $cost,
                        'condition' => $condition,
                        'status' => $status,
                        'remarks' => self::SAMPLE_MARKER.'; generated for application testing.',
                    ],
                );

                $assets[$index] = $asset;
            }

            $reports = [];
            foreach ($cases as $caseIndex => $case) {
                $asset = $assets[$case['asset_index']];
                $reportNumber = sprintf('SAMPLE-RPT-2026-%04d', $caseIndex + 1);
                $createdAt = now()->subDays(4 + (($caseIndex * 3) % 100))->setTime(9 + ($caseIndex % 8), 15);
                $report = AssetRequest::firstOrCreate(
                    ['reference_no' => $reportNumber],
                    [
                        'asset_id' => $asset->id,
                        'location_id' => $asset->location_id,
                        'reported_by' => $actors['end_users'][$caseIndex % count($actors['end_users'])]->id,
                        'type' => $case['report_type'],
                        'description' => self::SAMPLE_MARKER.' '.$case['problem'],
                        'photo_path' => null,
                        'status' => $caseIndex < 10 ? 'in_review' : 'resolved',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ],
                );
                $reports[$caseIndex] = $report;
            }

            foreach ($cases as $caseIndex => $case) {
                $asset = $assets[$case['asset_index']];
                $report = $reports[$caseIndex];
                $technician = $actors['maintenance_users'][$caseIndex % count($actors['maintenance_users'])];
                $reportNumber = sprintf('SAMPLE-RPT-2026-%04d', $caseIndex + 1);
                $taskNumber = sprintf('SAMPLE-MNT-2026-%04d', $caseIndex + 1);
                $stage = $caseIndex < 5 ? 'Pending' : ($caseIndex < 10 ? 'In Progress' : 'Completed');
                $reportedDate = $report->created_at->toDateString();
                $inspectionDate = $caseIndex < 5 ? null : Carbon::parse($reportedDate)->addDay()->toDateString();
                $startedAt = $caseIndex < 5
                    ? null
                    : Carbon::parse($inspectionDate)->setTime(8 + ($caseIndex % 8), 0);
                $completedAt = $stage === 'Completed'
                    ? $startedAt->copy()->addHours(2 + ($caseIndex % 5))
                    : null;
                $priority = $case['priority'];
                $taskTitle = sprintf('%s %s - %s', self::SAMPLE_MARKER, $taskNumber, $case['title']);
                $taskProblem = sprintf(
                    '%s | Reported %s: %s Damage: %s Initial condition/status: %s / %s.',
                    $reportNumber,
                    $reportedDate,
                    $case['problem'],
                    $case['damage'],
                    $caseIndex < 10 ? 'Poor' : $case['initial_condition'],
                    $caseIndex < 10 ? 'Under Maintenance' : $case['initial_status'],
                );
                $taskInstructions = $this->taskInstructions(
                    $case,
                    $asset,
                    $report,
                    $technician,
                    $inspectionDate,
                    $reportedDate,
                    $priority,
                );
                $taskRemarks = $this->taskRemarks(
                    $case,
                    $stage,
                    $reportNumber,
                    $taskNumber,
                    $technician,
                    $startedAt,
                    $completedAt,
                );
                $dueDate = Carbon::parse($reportedDate)->addDays(7)->toDateString();

                $task = MaintenanceTask::withTrashed()->firstOrCreate(
                    ['title' => $taskTitle],
                    [
                        'asset_id' => $asset->id,
                        'assigned_to' => $technician->id,
                        'assigned_by' => $actors['admin']->id,
                        'instructions' => $taskInstructions,
                        'priority' => $priority,
                        'due_date' => $dueDate,
                        'status' => $stage,
                        'problem' => $taskProblem,
                        'remarks' => $taskRemarks,
                        'started_at' => $startedAt,
                        'completed_at' => $completedAt,
                        'created_at' => $report->created_at,
                        'updated_at' => $completedAt ?? $startedAt ?? $report->created_at,
                    ],
                );
                $this->createTaskActivityHistory(
                    $task,
                    $asset,
                    $actors['admin'],
                    $technician,
                    $reportNumber,
                    $case,
                    $report->created_at,
                    $inspectionDate,
                    $completedAt,
                );

                if ($caseIndex >= 5) {
                    $this->createMaintenanceLog(
                        $asset,
                        $case,
                        $task,
                        $technician,
                        $taskNumber,
                        $completedAt ?? $startedAt,
                        $stage,
                    );
                }
            }

            $this->createRoutineLogs($assets, $actors['maintenance_users']);

            if ($this->command) {
                $this->command->info(
                    'Sample data ready: 200 tagged assets, 30 damage reports, 30 maintenance tasks, and 30 maintenance logs (existing sample rows were kept).',
                );
            }
        });
    }

    private function getActors(): array
    {
        $activeUsers = User::query()->where('is_active', true)->orderBy('id')->get();
        $admin = $activeUsers->firstWhere('role', config('roles.admin', 'admin'));
        $maintenanceUsers = $activeUsers->where('role', config('roles.maintenance', 'maintenance'))->values();
        $endUsers = $activeUsers->where('role', config('roles.end_user', 'end_user'))->values();

        if (! $admin || $maintenanceUsers->isEmpty() || $endUsers->isEmpty()) {
            throw new RuntimeException(
                'Sample data was not inserted. An active admin, maintenance user, and end_user must already exist. Create them through the existing admin user-management flow, then rerun this seeder.',
            );
        }

        return [
            'admin' => $admin,
            'maintenance_users' => $maintenanceUsers,
            'end_users' => $endUsers,
        ];
    }

    private function assertNoRealRecordCollisions(array $cases): void
    {
        $assetTags = array_map(
            fn (int $index): string => sprintf('DEMO-LGU-2026-%04d', $index + 1),
            range(0, 199),
        );

        foreach (Asset::withTrashed()->whereIn('asset_tag', $assetTags)->get(['asset_tag', 'remarks']) as $asset) {
            if (! str_starts_with((string) $asset->remarks, self::SAMPLE_MARKER)) {
                throw new RuntimeException(
                    sprintf('Sample seeding stopped: property number %s is already used by a non-sample asset.', $asset->asset_tag),
                );
            }
        }

        $reportNumbers = array_map(
            fn (int $index): string => sprintf('SAMPLE-RPT-2026-%04d', $index + 1),
            range(0, count($cases) - 1),
        );
        foreach (AssetRequest::query()->whereIn('reference_no', $reportNumbers)->get(['reference_no', 'description']) as $report) {
            if (! str_starts_with($report->description, self::SAMPLE_MARKER)) {
                throw new RuntimeException(
                    sprintf('Sample seeding stopped: report number %s is already used by a non-sample report.', $report->reference_no),
                );
            }
        }

        $taskTitles = [];
        foreach ($cases as $index => $case) {
            $taskTitles[] = sprintf(
                '%s SAMPLE-MNT-2026-%04d - %s',
                self::SAMPLE_MARKER,
                $index + 1,
                $case['title'],
            );
        }
        foreach (MaintenanceTask::withTrashed()->whereIn('title', $taskTitles)->get(['title', 'remarks']) as $task) {
            if (! str_starts_with((string) $task->remarks, self::SAMPLE_MARKER)) {
                throw new RuntimeException(
                    sprintf('Sample seeding stopped: maintenance task "%s" is already used by a non-sample task.', $task->title),
                );
            }
        }
    }

    private function assetState(int $index, array $reportedCases, array $cases): array
    {
        if (isset($reportedCases[$index])) {
            $caseIndex = $reportedCases[$index];
            if ($caseIndex < 10) {
                return ['Poor', 'Under Maintenance'];
            }

            return [$cases[$caseIndex]['final_condition'], $cases[$caseIndex]['final_status']];
        }

        if ($index % 57 === 0) {
            return ['Unserviceable', 'Disposed'];
        }

        if ($index % 9 === 0) {
            return ['Fair', 'In Use'];
        }

        return $index % 3 === 0 ? ['Good', 'Available'] : ['Good', 'In Use'];
    }

    private function taskInstructions(
        array $case,
        Asset $asset,
        AssetRequest $report,
        User $technician,
        ?string $inspectionDate,
        string $reportedDate,
        string $priority,
    ): string {
        return implode("\n", [
            'Department / location: '.$asset->location()->value('name').' / '.$asset->room_area,
            'Assigned user: '.$report->reporter()->value('name'),
            'Date reported: '.$reportedDate,
            'Date inspected: '.($inspectionDate ?? 'Pending inspection'),
            'Priority level: '.$priority,
            'Inspection findings: '.($inspectionDate ? $case['findings'] : 'Awaiting technician inspection.'),
            'Identified defective components: '.($inspectionDate ? $case['defects'] : 'To be determined.'),
            'Cause of damage: '.($inspectionDate ? $case['cause'] : 'To be determined.'),
            'Recommended action: '.$case['action'],
            'Required repairs: '.$case['required_repairs'],
            'Technician: '.$technician->name,
        ]);
    }

    private function taskRemarks(
        array $case,
        string $stage,
        string $reportNumber,
        string $taskNumber,
        User $technician,
        ?Carbon $startedAt,
        ?Carbon $completedAt,
    ): string {
        $workPerformed = match ($stage) {
            'Pending' => 'Not started; awaiting inspection.',
            'In Progress' => 'Completed initial inspection and fault isolation; repair work is in progress.',
            default => $case['work'],
        };
        $componentsRepaired = match ($stage) {
            'Pending' => 'Not yet determined.',
            'In Progress' => 'No components repaired to date.',
            default => $case['repaired'],
        };
        $componentsReplaced = match ($stage) {
            'Pending' => 'Not yet determined.',
            'In Progress' => 'No components replaced to date.',
            default => $case['replaced'],
        };
        $partsUsed = match ($stage) {
            'Pending' => 'None to date.',
            'In Progress' => 'Diagnostic supplies only; repair parts not installed.',
            default => $case['parts'],
        };
        $laborCost = $stage === 'Completed' ? $case['labor_cost'] : ($stage === 'In Progress' ? $case['labor_cost'] : 0);
        $materialsCost = $stage === 'Completed' ? $case['materials_cost'] : 0;

        $remarks = [
            self::SAMPLE_MARKER.' '.$taskNumber.'; linked report '.$reportNumber.'.',
            'Work performed: '.$workPerformed,
            'Components repaired: '.$componentsRepaired,
            'Components replaced: '.$componentsReplaced,
            'Parts and materials used: '.$partsUsed,
            'Labor cost: '.number_format($laborCost, 2),
            'Materials cost: '.number_format($materialsCost, 2),
            'Total maintenance cost: '.number_format($laborCost + $materialsCost, 2),
            'Technician: '.$technician->name,
            'Maintenance start date: '.($startedAt?->toDateString() ?? 'Not started'),
            'Maintenance completion date: '.($completedAt?->toDateString() ?? 'In progress'),
            'Final condition: '.($completedAt ? $case['final_condition'] : 'Pending'),
            'Final asset status: '.($completedAt ? $case['final_status'] : 'Under Maintenance'),
            'Repair result: '.($completedAt ? $case['result'] : 'Work remains in progress.'),
            'Testing results: '.($completedAt ? $case['testing'] : 'Final testing pending.'),
            'Technician remarks: '.$case['technician_remarks'],
            'Date completed: '.($completedAt?->toDateString() ?? 'Not completed'),
            'Verified by: '.($completedAt ? 'Property Custodian' : 'Pending custodian verification'),
            'Property Custodian approval: '.($completedAt ? 'Reviewed; sample approval recorded.' : 'Pending'),
        ];

        return implode("\n", $remarks);
    }

    private function createMaintenanceLog(
        Asset $asset,
        array $case,
        MaintenanceTask $task,
        User $technician,
        string $taskNumber,
        Carbon $date,
        string $stage,
    ): void {
        $remarks = sprintf('%s %s; task %s; stage %s.', self::SAMPLE_MARKER, $taskNumber, $task->id, $stage);
        $date = $date->toDateString();

        MaintenanceLog::firstOrCreate(
            ['remarks' => $remarks],
            [
                'asset_id' => $asset->id,
                'maintenance_type' => $stage === 'Completed' ? 'Repair' : 'Inspection',
                'date' => $date,
                'performed_by' => $technician->name,
                'description_of_work' => $stage === 'Completed'
                    ? $case['work']
                    : 'Initial inspection and diagnostic work completed; repair remains in progress.',
                'parts_replaced' => $stage === 'Completed' ? $case['replaced'] : null,
                'cost' => $stage === 'Completed'
                    ? $case['labor_cost'] + $case['materials_cost']
                    : $case['labor_cost'],
            ],
        );
    }

    private function createTaskActivityHistory(
        MaintenanceTask $task,
        Asset $asset,
        User $admin,
        User $technician,
        string $reportNumber,
        array $case,
        Carbon $reportedAt,
        ?string $inspectionDate,
        ?Carbon $completedAt,
    ): void {
        $events = [[
            'user' => $admin,
            'action' => 'task_assigned',
            'description' => sprintf(
                '%s Report %s reviewed and forwarded to %s for inspection.',
                self::SAMPLE_MARKER,
                $reportNumber,
                $technician->name,
            ),
            'created_at' => $reportedAt,
        ], [
            'user' => $admin,
            'action' => 'end_user_report_linked',
            'description' => 'Linked end-user report: '.$reportNumber.'.',
            'created_at' => $reportedAt,
        ]];

        if ($inspectionDate) {
            $inspectionAt = Carbon::parse($inspectionDate)->setTime(8, 30);
            $events[] = [
                'user' => $technician,
                'action' => 'maintenance_inspection_recorded',
                'description' => sprintf(
                    '%s Report %s inspected. Findings: %s Recommended action: %s',
                    self::SAMPLE_MARKER,
                    $reportNumber,
                    $case['findings'],
                    $case['action'],
                ),
                'created_at' => $inspectionAt,
            ];
        }

        if ($completedAt) {
            $events[] = [
                'user' => $technician,
                'action' => 'maintenance_task_completed',
                'description' => sprintf(
                    '%s Report %s completed. Final condition: %s; final status: %s.',
                    self::SAMPLE_MARKER,
                    $reportNumber,
                    $case['final_condition'],
                    $case['final_status'],
                ),
                'created_at' => $completedAt,
            ];
        }

        foreach ($events as $event) {
            ActivityLog::firstOrCreate(
                ['description' => $event['description']],
                [
                    'user_id' => $event['user']->id,
                    'asset_id' => $asset->id,
                    'maintenance_task_id' => $task->id,
                    'action' => $event['action'],
                    'created_at' => $event['created_at'],
                ],
            );
        }
    }

    private function createRoutineLogs(array $assets, $maintenanceUsers): void
    {
        $routines = [
            ['Preventive', 'Cleaned vents and checked safe operation.', 'Cleaning supplies', 350],
            ['Inspection', 'Completed a scheduled visual and functional inspection.', null, 0],
            ['Cleaning', 'Cleaned equipment surfaces and verified controls.', 'Approved cleaning materials', 280],
            ['Preventive', 'Checked power connections and tightened accessible fasteners.', 'Electrical tape and fasteners', 420],
            ['Inspection', 'Checked equipment condition and documented an acceptable operating test.', null, 0],
        ];

        foreach ($routines as $routineIndex => [$type, $work, $parts, $cost]) {
            $asset = $assets[30 + $routineIndex];
            $technician = $maintenanceUsers[$routineIndex % count($maintenanceUsers)];
            $date = now()->subDays(18 + ($routineIndex * 11))->toDateString();
            $remarks = sprintf('%s SAMPLE-MNT-HIST-%04d; routine sample history.', self::SAMPLE_MARKER, $routineIndex + 1);

            MaintenanceLog::firstOrCreate(
                ['remarks' => $remarks],
                [
                    'asset_id' => $asset->id,
                    'maintenance_type' => $type,
                    'date' => $date,
                    'performed_by' => $technician->name,
                    'description_of_work' => $work,
                    'parts_replaced' => $parts,
                    'cost' => $cost,
                ],
            );
        }
    }

    private function locations(): array
    {
        return [
            'Municipal Hall - Administration',
            'Municipal Health Office',
            'Municipal Agriculture Office',
            'Municipal Engineering Office',
            'Municipal Disaster Risk Reduction Office',
            'Municipal Social Welfare Office',
            'Municipal Treasurer Office',
            'Municipal Assessor Office',
            'Municipal Planning and Development Office',
            'Municipal Tourism Office',
            'Municipal Sports and Recreation Office',
            'Municipal Waterworks and General Services',
            'Municipal Library',
            'Municipal Warehouse',
        ];
    }

    private function rooms(): array
    {
        return [
            'Ground Floor - Records Room',
            'Ground Floor - Public Service Counter',
            'Second Floor - Conference Room',
            'Second Floor - Information Technology Room',
            'Main Office',
            'Equipment Storage Area',
            'Service Garage',
            'Clinic Examination Room',
            'Emergency Response Bay',
            'Covered Court',
        ];
    }

    private function assetCatalog(): array
    {
        return [
            [
                'category' => 'Computer and IT Equipment',
                'code' => 'IT',
                'items' => [
                    ['name' => 'Desktop computer', 'description' => 'Business desktop workstation with keyboard and mouse', 'brand' => 'Dell', 'model' => 'OptiPlex 7010', 'cost' => 48500, 'serial' => true],
                    ['name' => 'Laptop computer', 'description' => 'Portable office laptop with power adapter', 'brand' => 'Lenovo', 'model' => 'ThinkPad E14', 'cost' => 55900, 'serial' => true],
                    ['name' => 'LCD monitor', 'description' => 'LED display for office workstation', 'brand' => 'AOC', 'model' => '24B2XH', 'cost' => 8900, 'serial' => true],
                    ['name' => 'Uninterruptible power supply', 'description' => 'Line-interactive backup power unit for computer equipment', 'brand' => 'APC', 'model' => 'BX1100C', 'cost' => 7200, 'serial' => true],
                    ['name' => 'Local network server', 'description' => 'Tower server for municipal file and application services', 'brand' => 'HPE', 'model' => 'ProLiant ML30', 'cost' => 186000, 'serial' => true],
                    ['name' => 'Network switch', 'description' => 'Managed gigabit Ethernet switch for office connectivity', 'brand' => 'Cisco', 'model' => 'CBS350-24T', 'cost' => 23800, 'serial' => true],
                    ['name' => 'Office tablet', 'description' => 'Tablet for field data collection and inspections', 'brand' => 'Samsung', 'model' => 'Galaxy Tab A9+', 'cost' => 18900, 'serial' => true],
                    ['name' => 'External backup drive', 'description' => 'Portable storage drive for scheduled file backups', 'brand' => 'Seagate', 'model' => 'Expansion 2TB', 'cost' => 5200, 'serial' => true],
                    ['name' => 'Wireless network router', 'description' => 'Dual-band wireless router for municipal office use', 'brand' => 'TP-Link', 'model' => 'Archer AX55', 'cost' => 6900, 'serial' => true],
                    ['name' => 'All-in-one computer', 'description' => 'Integrated desktop computer for public service counter', 'brand' => 'HP', 'model' => 'ProOne 440 G9', 'cost' => 62900, 'serial' => true],
                ],
            ],
            [
                'category' => 'Office Furniture',
                'code' => 'FUR',
                'items' => [
                    ['name' => 'Office table', 'description' => 'Melamine executive office table with drawers', 'brand' => 'Local supplier', 'model' => 'Executive 1500', 'cost' => 12800, 'serial' => false],
                    ['name' => 'Ergonomic office chair', 'description' => 'Adjustable swivel office chair with caster wheels', 'brand' => 'Fursys', 'model' => 'T50', 'cost' => 8900, 'serial' => false],
                    ['name' => 'Steel filing cabinet', 'description' => 'Four-drawer lockable cabinet for municipal records', 'brand' => 'Local supplier', 'model' => 'FC-4D', 'cost' => 9700, 'serial' => false],
                    ['name' => 'Conference table', 'description' => 'Large meeting table for committee and council sessions', 'brand' => 'Local supplier', 'model' => 'CT-2400', 'cost' => 28900, 'serial' => false],
                    ['name' => 'Visitor chair', 'description' => 'Padded steel-frame chair for public waiting area', 'brand' => 'Local supplier', 'model' => 'VC-01', 'cost' => 2850, 'serial' => false],
                    ['name' => 'Bookshelf', 'description' => 'Steel shelving unit for office files and reference books', 'brand' => 'Federal', 'model' => 'FS-5T', 'cost' => 6800, 'serial' => false],
                    ['name' => 'Reception counter', 'description' => 'Public service reception counter with document shelves', 'brand' => 'Local supplier', 'model' => 'RC-180', 'cost' => 22300, 'serial' => false],
                    ['name' => 'Folding table', 'description' => 'Portable folding table for community meetings and events', 'brand' => 'Local supplier', 'model' => 'FT-6', 'cost' => 4200, 'serial' => false],
                    ['name' => 'Steel office cabinet', 'description' => 'Lockable two-door steel storage cabinet', 'brand' => 'Federal', 'model' => 'SC-2D', 'cost' => 8400, 'serial' => false],
                    ['name' => 'Visitor sofa', 'description' => 'Three-seat upholstered sofa for municipal reception area', 'brand' => 'Local supplier', 'model' => 'VS-3', 'cost' => 18400, 'serial' => false],
                ],
            ],
            [
                'category' => 'Office Equipment',
                'code' => 'OFE',
                'items' => [
                    ['name' => 'Multifunction laser printer', 'description' => 'Network printer, copier and scanner for office documents', 'brand' => 'Brother', 'model' => 'MFC-L6900DW', 'cost' => 48500, 'serial' => true],
                    ['name' => 'LCD projector', 'description' => 'Portable projector for council presentations and training', 'brand' => 'Epson', 'model' => 'EB-E20', 'cost' => 34900, 'serial' => true],
                    ['name' => 'Document scanner', 'description' => 'Duplex document scanner for records digitization', 'brand' => 'Fujitsu', 'model' => 'fi-8170', 'cost' => 52900, 'serial' => true],
                    ['name' => 'Photocopier', 'description' => 'Department copier with automatic document feeder', 'brand' => 'Canon', 'model' => 'imageRUNNER 2425', 'cost' => 142000, 'serial' => true],
                    ['name' => 'Paper shredder', 'description' => 'Cross-cut shredder for confidential office records', 'brand' => 'Fellowes', 'model' => 'Powershred 99Ci', 'cost' => 18900, 'serial' => true],
                    ['name' => 'Binding machine', 'description' => 'Comb binding machine for reports and reference manuals', 'brand' => 'GBC', 'model' => 'CombBind C200', 'cost' => 11900, 'serial' => true],
                    ['name' => 'Laminating machine', 'description' => 'A3 laminator for public notices and identification cards', 'brand' => 'Fellowes', 'model' => 'Saturn 3i A3', 'cost' => 8900, 'serial' => true],
                    ['name' => 'Digital weighing scale', 'description' => 'Platform scale for municipal warehouse inventory', 'brand' => 'A&D', 'model' => 'FG-60KAL', 'cost' => 15900, 'serial' => true],
                    ['name' => 'Document camera', 'description' => 'Visual presenter for meetings and public briefings', 'brand' => 'Aver', 'model' => 'F17-8M', 'cost' => 26500, 'serial' => true],
                    ['name' => 'Cash counting machine', 'description' => 'Banknote counter for municipal treasury operations', 'brand' => 'Ribao', 'model' => 'BC-55', 'cost' => 19200, 'serial' => true],
                ],
            ],
            [
                'category' => 'Communication Equipment',
                'code' => 'COM',
                'items' => [
                    ['name' => 'Handheld two-way radio', 'description' => 'Portable VHF radio for field coordination', 'brand' => 'Motorola', 'model' => 'EP450', 'cost' => 14900, 'serial' => true],
                    ['name' => 'Base radio transceiver', 'description' => 'Fixed radio transceiver for emergency operations center', 'brand' => 'Icom', 'model' => 'IC-F5061', 'cost' => 38600, 'serial' => true],
                    ['name' => 'Public address amplifier', 'description' => 'Audio amplifier for municipal announcements and events', 'brand' => 'TOA', 'model' => 'A-2120', 'cost' => 24800, 'serial' => true],
                    ['name' => 'Wireless microphone set', 'description' => 'Dual-channel microphone system for public meetings', 'brand' => 'Shure', 'model' => 'BLX288', 'cost' => 32900, 'serial' => true],
                    ['name' => 'Conference telephone', 'description' => 'Speakerphone for inter-office and remote meetings', 'brand' => 'Yealink', 'model' => 'CP965', 'cost' => 28400, 'serial' => true],
                    ['name' => 'Telephone handset', 'description' => 'Cordless office telephone with charging base', 'brand' => 'Panasonic', 'model' => 'KX-TG3711', 'cost' => 2850, 'serial' => true],
                    ['name' => 'Radio antenna assembly', 'description' => 'Omnidirectional base antenna and mounting hardware', 'brand' => 'Diamond', 'model' => 'X-50A', 'cost' => 7800, 'serial' => true],
                    ['name' => 'Megaphone', 'description' => 'Battery-operated megaphone for crowd and evacuation guidance', 'brand' => 'Pyle', 'model' => 'PMP40', 'cost' => 3900, 'serial' => true],
                    ['name' => 'Network video intercom', 'description' => 'Door communication unit for municipal office entrance', 'brand' => 'Hikvision', 'model' => 'DS-KH6320', 'cost' => 12700, 'serial' => true],
                    ['name' => 'Portable field radio', 'description' => 'Rugged handheld radio for inspection and response teams', 'brand' => 'Kenwood', 'model' => 'TK-3000', 'cost' => 13800, 'serial' => true],
                ],
            ],
            [
                'category' => 'Vehicles and Transportation',
                'code' => 'VEH',
                'items' => [
                    ['name' => 'Municipal service pickup', 'description' => 'Four-wheel-drive pickup for field inspections and office service', 'brand' => 'Toyota', 'model' => 'Hilux 2.4 G 4x4', 'cost' => 1845000, 'serial' => true],
                    ['name' => 'Municipal ambulance', 'description' => 'Patient transport vehicle with basic emergency conversion', 'brand' => 'Toyota', 'model' => 'Hiace Commuter Ambulance', 'cost' => 2980000, 'serial' => true],
                    ['name' => 'Service motorcycle', 'description' => 'Utility motorcycle for document and field transport', 'brand' => 'Honda', 'model' => 'TMX 125 Alpha', 'cost' => 79500, 'serial' => true],
                    ['name' => 'Municipal dump truck', 'description' => 'Light-duty dump truck for public works and hauling', 'brand' => 'Isuzu', 'model' => 'NKR 4.8 Dump', 'cost' => 2890000, 'serial' => true],
                    ['name' => 'Passenger service van', 'description' => 'Passenger van for municipal personnel and community programs', 'brand' => 'Toyota', 'model' => 'Hiace GL Grandia', 'cost' => 2390000, 'serial' => true],
                    ['name' => 'Rescue utility vehicle', 'description' => 'Utility vehicle configured for disaster response support', 'brand' => 'Mitsubishi', 'model' => 'L300 FB', 'cost' => 1150000, 'serial' => true],
                    ['name' => 'Municipal fire truck', 'description' => 'Fire response truck with water tank and pump assembly', 'brand' => 'Hino', 'model' => '300 Series Fire Unit', 'cost' => 5680000, 'serial' => true],
                    ['name' => 'Road maintenance vehicle', 'description' => 'Utility vehicle for engineering and road maintenance crews', 'brand' => 'Mitsubishi', 'model' => 'Strada GLX', 'cost' => 1560000, 'serial' => true],
                    ['name' => 'Passenger multicab', 'description' => 'Compact service vehicle for short municipal trips', 'brand' => 'Suzuki', 'model' => 'Carry Utility', 'cost' => 645000, 'serial' => true],
                    ['name' => 'Motorized tricycle', 'description' => 'Three-wheel utility vehicle for local transport and errands', 'brand' => 'Kawasaki', 'model' => 'Barako II Sidecar', 'cost' => 168000, 'serial' => true],
                ],
            ],
            [
                'category' => 'Medical Equipment',
                'code' => 'MED',
                'items' => [
                    ['name' => 'Digital blood pressure monitor', 'description' => 'Automatic upper-arm monitor for health center consultations', 'brand' => 'Omron', 'model' => 'HEM-7120', 'cost' => 2850, 'serial' => true],
                    ['name' => 'Oxygen concentrator', 'description' => 'Medical oxygen concentrator for health center patient support', 'brand' => 'Yuwell', 'model' => '8F-5A', 'cost' => 38900, 'serial' => true],
                    ['name' => 'Autoclave sterilizer', 'description' => 'Tabletop steam sterilizer for reusable clinic instruments', 'brand' => 'Tuttnauer', 'model' => '3870EA', 'cost' => 98600, 'serial' => true],
                    ['name' => 'Examination bed', 'description' => 'Adjustable examination couch for municipal clinic', 'brand' => 'Local medical supplier', 'model' => 'EB-200', 'cost' => 18700, 'serial' => false],
                    ['name' => 'Medical refrigerator', 'description' => 'Temperature-controlled refrigerator for clinic supplies', 'brand' => 'Haier', 'model' => 'HYC-260', 'cost' => 129000, 'serial' => true],
                    ['name' => 'Nebulizer unit', 'description' => 'Compressor nebulizer for outpatient respiratory care', 'brand' => 'Omron', 'model' => 'NE-C28', 'cost' => 3900, 'serial' => true],
                    ['name' => 'Patient weighing scale', 'description' => 'Digital adult weighing scale for health center screening', 'brand' => 'Detecto', 'model' => '758C', 'cost' => 16800, 'serial' => true],
                    ['name' => 'Examination lamp', 'description' => 'Adjustable LED examination lamp for clinic procedures', 'brand' => 'Burton', 'model' => 'AvaLED', 'cost' => 42900, 'serial' => true],
                    ['name' => 'Oxygen cylinder with regulator', 'description' => 'Medical oxygen cylinder and flow regulator assembly', 'brand' => 'Lifeline', 'model' => 'M6 Assembly', 'cost' => 11800, 'serial' => true],
                    ['name' => 'Emergency stretcher', 'description' => 'Foldable patient transfer stretcher for emergency response', 'brand' => 'Ferno', 'model' => 'Model 11', 'cost' => 29800, 'serial' => true],
                ],
            ],
            [
                'category' => 'Electrical Equipment',
                'code' => 'ELE',
                'items' => [
                    ['name' => 'Split-type air conditioner', 'description' => 'Inverter air-conditioning unit for municipal office', 'brand' => 'Panasonic', 'model' => 'CS-XU12WKQ', 'cost' => 46500, 'serial' => true],
                    ['name' => 'Electric stand fan', 'description' => 'Oscillating electric stand fan for office ventilation', 'brand' => 'Asahi', 'model' => 'SF-600', 'cost' => 3850, 'serial' => true],
                    ['name' => 'Diesel generator set', 'description' => 'Standby generator for municipal emergency power', 'brand' => 'Perkins', 'model' => 'P55-3', 'cost' => 385000, 'serial' => true],
                    ['name' => 'Water pump', 'description' => 'Electric centrifugal pump for municipal facility water supply', 'brand' => 'Grundfos', 'model' => 'CM 5-4', 'cost' => 27900, 'serial' => true],
                    ['name' => 'Voltage regulator', 'description' => 'Automatic voltage regulator for sensitive office equipment', 'brand' => 'Stac', 'model' => 'AVR-5K', 'cost' => 12800, 'serial' => true],
                    ['name' => 'Electrical distribution panel', 'description' => 'Commercial distribution panel for municipal facility circuits', 'brand' => 'Schneider Electric', 'model' => 'PrismaSeT', 'cost' => 58500, 'serial' => true],
                    ['name' => 'Solar street light assembly', 'description' => 'Solar LED streetlight with panel, battery and pole assembly', 'brand' => 'Solaric', 'model' => 'SL-120', 'cost' => 24900, 'serial' => true],
                    ['name' => 'Emergency lighting unit', 'description' => 'Rechargeable emergency light for municipal building corridors', 'brand' => 'Firefly', 'model' => 'EL-36', 'cost' => 1950, 'serial' => true],
                    ['name' => 'Electrical transformer', 'description' => 'Pole-mounted distribution transformer for public facility', 'brand' => 'Meralco Industrial', 'model' => 'T-25KVA', 'cost' => 168000, 'serial' => true],
                    ['name' => 'Power extension reel', 'description' => 'Heavy-duty cable reel for maintenance and event work', 'brand' => 'Intex', 'model' => 'ER-30M', 'cost' => 4300, 'serial' => true],
                ],
            ],
            [
                'category' => 'Maintenance Equipment',
                'code' => 'MNT',
                'items' => [
                    ['name' => 'Grass cutter', 'description' => 'Gasoline-powered brush cutter for municipal grounds maintenance', 'brand' => 'Makita', 'model' => 'EM4351UH', 'cost' => 23900, 'serial' => true],
                    ['name' => 'Welding machine', 'description' => 'Inverter arc welding machine for metal repair work', 'brand' => 'Lincoln Electric', 'model' => 'Invertec 170S', 'cost' => 18600, 'serial' => true],
                    ['name' => 'Pressure washer', 'description' => 'Portable pressure washer for facility and vehicle cleaning', 'brand' => 'Karcher', 'model' => 'K4 Power Control', 'cost' => 22900, 'serial' => true],
                    ['name' => 'Portable air compressor', 'description' => 'Workshop compressor for maintenance and tire service', 'brand' => 'Ingco', 'model' => 'AC20248', 'cost' => 14800, 'serial' => true],
                    ['name' => 'Power tool set', 'description' => 'Cordless drill and driver set for general repair work', 'brand' => 'Bosch', 'model' => 'GSR 120-LI Set', 'cost' => 11900, 'serial' => true],
                    ['name' => 'Extension ladder', 'description' => 'Aluminum extension ladder for building maintenance', 'brand' => 'Bestray', 'model' => 'EL-24', 'cost' => 12800, 'serial' => false],
                    ['name' => 'Concrete mixer', 'description' => 'Portable concrete mixer for municipal repair projects', 'brand' => 'Makute', 'model' => 'CM-160', 'cost' => 38900, 'serial' => true],
                    ['name' => 'Water dewatering pump', 'description' => 'Portable gasoline pump for flood and drainage response', 'brand' => 'Honda', 'model' => 'WB30XT', 'cost' => 32800, 'serial' => true],
                    ['name' => 'Bench grinder', 'description' => 'Workshop bench grinder for tool sharpening and repair', 'brand' => 'Makita', 'model' => 'GB801', 'cost' => 15200, 'serial' => true],
                    ['name' => 'Portable generator', 'description' => 'Portable gasoline generator for field maintenance tasks', 'brand' => 'Honda', 'model' => 'EG6500CXS', 'cost' => 73500, 'serial' => true],
                ],
            ],
            [
                'category' => 'Sports Equipment',
                'code' => 'SPT',
                'items' => [
                    ['name' => 'Basketball goal assembly', 'description' => 'Steel basketball goal and backboard for municipal court', 'brand' => 'Local fabricator', 'model' => 'BG-305', 'cost' => 38500, 'serial' => false],
                    ['name' => 'Volleyball net and post set', 'description' => 'Competition net with adjustable steel support posts', 'brand' => 'Mikasa', 'model' => 'VNS-200', 'cost' => 16800, 'serial' => false],
                    ['name' => 'Portable bleacher', 'description' => 'Four-row aluminum bleacher for community sports events', 'brand' => 'Local fabricator', 'model' => 'PB-4R', 'cost' => 89500, 'serial' => false],
                    ['name' => 'Table tennis table', 'description' => 'Foldable table tennis table with net and wheels', 'brand' => 'Stiga', 'model' => 'Expert Roller', 'cost' => 42600, 'serial' => true],
                    ['name' => 'Football goal pair', 'description' => 'Steel football goals for municipal playing field', 'brand' => 'Local fabricator', 'model' => 'FG-7A', 'cost' => 58900, 'serial' => false],
                    ['name' => 'Portable sound system', 'description' => 'Powered speakers and mixer for sports programs and events', 'brand' => 'JBL', 'model' => 'EON712 Pair', 'cost' => 89800, 'serial' => true],
                    ['name' => 'Badminton court set', 'description' => 'Portable badminton posts, net and court equipment', 'brand' => 'Yonex', 'model' => 'BCS-01', 'cost' => 12700, 'serial' => false],
                    ['name' => 'Gymnastics mat set', 'description' => 'Foam safety mats for municipal youth sports activities', 'brand' => 'Local supplier', 'model' => 'GM-20', 'cost' => 24900, 'serial' => false],
                    ['name' => 'Starting pistol', 'description' => 'Electronic starting signal device for track events', 'brand' => 'Seiko', 'model' => 'SP-100', 'cost' => 21900, 'serial' => true],
                    ['name' => 'Sports equipment storage rack', 'description' => 'Lockable steel rack for balls and training equipment', 'brand' => 'Local fabricator', 'model' => 'SR-12', 'cost' => 13900, 'serial' => false],
                ],
            ],
            [
                'category' => 'Buildings and Land Properties',
                'code' => 'BLD',
                'items' => [
                    ['name' => 'Municipal Hall building', 'description' => 'Municipal government office building and fixed improvements', 'brand' => null, 'model' => 'Government building', 'cost' => 18500000, 'serial' => false],
                    ['name' => 'Municipal health center building', 'description' => 'Health center building and permanent clinic improvements', 'brand' => null, 'model' => 'Health facility', 'cost' => 7600000, 'serial' => false],
                    ['name' => 'Municipal warehouse building', 'description' => 'Concrete municipal warehouse building and storage improvements', 'brand' => null, 'model' => 'Warehouse facility', 'cost' => 4950000, 'serial' => false],
                    ['name' => 'Municipal lot', 'description' => 'Municipal land parcel held for public service use', 'brand' => null, 'model' => 'Land property', 'cost' => 3200000, 'serial' => false],
                    ['name' => 'Municipal covered court', 'description' => 'Covered court structure and permanent sports facility improvements', 'brand' => null, 'model' => 'Sports facility', 'cost' => 9250000, 'serial' => false],
                    ['name' => 'Municipal library building', 'description' => 'Public library building and permanent improvements', 'brand' => null, 'model' => 'Library facility', 'cost' => 6350000, 'serial' => false],
                    ['name' => 'Municipal evacuation center', 'description' => 'Evacuation center building for emergency shelter and response', 'brand' => null, 'model' => 'Emergency facility', 'cost' => 11200000, 'serial' => false],
                    ['name' => 'Municipal waterworks facility', 'description' => 'Waterworks operations building and fixed facility improvements', 'brand' => null, 'model' => 'Utility facility', 'cost' => 5700000, 'serial' => false],
                    ['name' => 'Municipal materials recovery facility', 'description' => 'Materials recovery facility building and site improvements', 'brand' => null, 'model' => 'Environmental facility', 'cost' => 4850000, 'serial' => false],
                    ['name' => 'Municipal engineering office building', 'description' => 'Engineering office building and permanent improvements', 'brand' => null, 'model' => 'Office facility', 'cost' => 6950000, 'serial' => false],
                ],
            ],
        ];
    }

    private function maintenanceCases(): array
    {
        $items = [
            ['asset_index' => 0, 'title' => 'Desktop computer will not power on', 'problem' => 'Desktop computer did not start during morning duty.', 'damage' => 'No power indicator or fan activity.', 'findings' => 'Power outlet tested normally; computer power supply failed under load.', 'defects' => 'ATX power supply and front-panel switch cable.', 'cause' => 'Worn power supply after extended service.', 'action' => 'Replace the power supply and perform a full hardware test.', 'required_repairs' => 'Replace 500 W power supply; verify storage and operating system.', 'work' => 'Replaced power supply, reseated memory and storage connections, and ran a two-hour stability test.', 'repaired' => 'Power connections and memory seating.', 'replaced' => '500 W power supply.', 'parts' => 'ATX 500 W power supply, cable ties.', 'labor_cost' => 650, 'materials_cost' => 2850, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Repaired and returned to service.', 'testing' => 'Passed repeated boot, memory, storage, and network checks.', 'technician_remarks' => 'Unit is stable and returned to the assigned office.', 'priority' => 'High', 'report_type' => 'malfunction'],
            ['asset_index' => 40, 'title' => 'Printer producing blank pages', 'problem' => 'Department printer fed paper but produced blank pages.', 'damage' => 'Print output was blank despite normal feed.', 'findings' => 'Toner cartridge was empty and imaging drum had uneven wear.', 'defects' => 'Toner cartridge and imaging drum.', 'cause' => 'Consumables reached end of service life.', 'action' => 'Replace consumables and clean the paper path.', 'required_repairs' => 'Replace toner and drum; calibrate print density.', 'work' => 'Cleaned the paper path, installed a new toner cartridge and drum, and printed test sheets.', 'repaired' => 'Paper feed path and print-density settings.', 'replaced' => 'Toner cartridge and drum unit.', 'parts' => 'Black toner cartridge, drum unit, cleaning cloth.', 'labor_cost' => 500, 'materials_cost' => 6200, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Repaired and available to the department.', 'testing' => 'Ten-page test set printed with readable text and no streaks.', 'technician_remarks' => 'Consumables recorded for routine replenishment.', 'priority' => 'Medium', 'report_type' => 'damage'],
            ['asset_index' => 21, 'title' => 'Office chair with broken caster wheels', 'problem' => 'Office chair leaned to one side and would not roll safely.', 'damage' => 'Two caster wheels detached from the chair base.', 'findings' => 'Two caster sockets were cracked; chair frame and gas lift were serviceable.', 'defects' => 'Two caster wheels and damaged socket inserts.', 'cause' => 'Normal wear and repeated loading.', 'action' => 'Replace caster set and inspect the base.', 'required_repairs' => 'Install five matching casters and tighten the base assembly.', 'work' => 'Removed damaged casters, installed a matching set, and checked chair stability.', 'repaired' => 'Chair base assembly.', 'replaced' => 'Five caster wheels.', 'parts' => 'Five heavy-duty caster wheels.', 'labor_cost' => 350, 'materials_cost' => 950, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Repaired; chair returned to office.', 'testing' => 'Chair rolled and remained stable under normal seated load.', 'technician_remarks' => 'Advise users not to overload or drag the chair.', 'priority' => 'Low', 'report_type' => 'damage'],
            ['asset_index' => 120, 'title' => 'Air conditioner not cooling', 'problem' => 'Office air conditioner ran but room temperature did not decrease.', 'damage' => 'Weak airflow and no effective cooling.', 'findings' => 'Clogged filter and low refrigerant pressure; no compressor fault found.', 'defects' => 'Air filter and refrigerant charge.', 'cause' => 'Deferred filter cleaning and a minor service-line leak.', 'action' => 'Clean filter, repair the flare connection, and recharge refrigerant.', 'required_repairs' => 'Seal the line, vacuum the system, recharge, and monitor pressure.', 'work' => 'Cleaned filter and coils, resealed flare connection, recharged refrigerant, and checked condensate drain.', 'repaired' => 'Refrigerant line and condensate drain.', 'replaced' => 'Air filter.', 'parts' => 'Replacement filter, flare seal, R-32 refrigerant.', 'labor_cost' => 1800, 'materials_cost' => 3450, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Fair', 'final_status' => 'In Use', 'result' => 'Cooling restored; monitor during normal office operation.', 'testing' => 'Supply air cooled normally during a 30-minute test; no leak observed.', 'technician_remarks' => 'Schedule quarterly filter cleaning.', 'priority' => 'High', 'report_type' => 'malfunction'],
            ['asset_index' => 1, 'title' => 'Laptop overheating during office work', 'problem' => 'Laptop became unusually hot and shut down during routine work.', 'damage' => 'Excessive heat, loud fan noise, and unexpected shutdown.', 'findings' => 'Cooling vents were obstructed by dust and thermal compound had dried.', 'defects' => 'Cooling fan and thermal interface compound.', 'cause' => 'Dust accumulation and age-related thermal compound degradation.', 'action' => 'Clean the cooling assembly and replace the fan if noise remains.', 'required_repairs' => 'Clean heat sink, apply thermal compound, and check fan speed.', 'work' => 'Removed dust, replaced thermal compound, cleaned the fan, and ran a processor stress test.', 'repaired' => 'Heat sink and fan assembly.', 'replaced' => 'Thermal interface compound.', 'parts' => 'Thermal compound and cleaning materials.', 'labor_cost' => 900, 'materials_cost' => 480, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Stable operation restored.', 'testing' => 'Passed 45-minute stress test without thermal shutdown.', 'technician_remarks' => 'Keep air vents clear during use.', 'priority' => 'High', 'report_type' => 'malfunction'],
            ['asset_index' => 2, 'title' => 'Monitor displaying horizontal lines', 'problem' => 'Monitor displayed intermittent horizontal lines.', 'damage' => 'Horizontal lines appeared after the display warmed up.', 'findings' => 'Display cable connector was loose; panel passed an external display test.', 'defects' => 'HDMI cable and connector.', 'cause' => 'Worn cable and loose connection.', 'action' => 'Replace cable and retest at native resolution.', 'required_repairs' => 'Install new HDMI cable and secure connector.', 'work' => 'Replaced the display cable, cleaned connectors, and tested multiple resolutions.', 'repaired' => 'Display connection.', 'replaced' => 'HDMI cable.', 'parts' => 'Two-meter HDMI cable.', 'labor_cost' => 300, 'materials_cost' => 480, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Display image restored.', 'testing' => 'No lines observed during a one-hour test at native resolution.', 'technician_remarks' => 'Panel is serviceable; no replacement required.', 'priority' => 'Medium', 'report_type' => 'damage'],
            ['asset_index' => 41, 'title' => 'Projector not displaying presentation image', 'problem' => 'Projector powered on but showed no laptop image.', 'damage' => 'Input signal not detected on either HDMI source.', 'findings' => 'HDMI adapter was faulty; projector lamp and input board passed checks.', 'defects' => 'HDMI adapter and source cable.', 'cause' => 'Damaged adapter from repeated setup and pack-down.', 'action' => 'Replace adapter and label the presentation cables.', 'required_repairs' => 'Install compatible adapter and verify all inputs.', 'work' => 'Replaced the HDMI adapter, cleaned the filter, and tested with two laptops.', 'repaired' => 'Input setup and air filter.', 'replaced' => 'HDMI adapter.', 'parts' => 'HDMI adapter, compressed air filter cleaner.', 'labor_cost' => 450, 'materials_cost' => 1250, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Presentation image restored.', 'testing' => 'Image and audio tested from two HDMI sources for 30 minutes.', 'technician_remarks' => 'Store the adapter with the projector case.', 'priority' => 'Medium', 'report_type' => 'malfunction'],
            ['asset_index' => 20, 'title' => 'Office table with cracked support leg', 'problem' => 'Office table rocked and one support leg was visibly cracked.', 'damage' => 'Cracked leg brace and loose fasteners.', 'findings' => 'Tabletop was intact; one leg brace and four fasteners required replacement.', 'defects' => 'Steel leg brace and mounting fasteners.', 'cause' => 'Repeated movement on an uneven floor.', 'action' => 'Replace brace and level the table.', 'required_repairs' => 'Fit a replacement brace and secure all leg connections.', 'work' => 'Replaced the support brace, tightened the frame, and fitted floor levelers.', 'repaired' => 'Table frame and leveling.', 'replaced' => 'Leg brace and fasteners.', 'parts' => 'Steel support brace, bolts, floor levelers.', 'labor_cost' => 500, 'materials_cost' => 850, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Stable and returned to office use.', 'testing' => 'Table remained level and stable under normal office load.', 'technician_remarks' => 'Avoid dragging the table across the floor.', 'priority' => 'Low', 'report_type' => 'damage'],
            ['asset_index' => 122, 'title' => 'Electric fan does not run', 'problem' => 'Electric fan did not start when switched on.', 'damage' => 'No blade movement; humming sound at high setting.', 'findings' => 'Capacitor was out of specification; motor winding remained serviceable.', 'defects' => 'Run capacitor and speed selector contact.', 'cause' => 'Capacitor deterioration from age and heat.', 'action' => 'Replace capacitor and clean motor assembly.', 'required_repairs' => 'Install matching capacitor and test all speeds.', 'work' => 'Replaced the capacitor, cleaned the motor, and lubricated the oscillation mechanism.', 'repaired' => 'Motor and oscillation mechanism.', 'replaced' => 'Run capacitor.', 'parts' => '1.5 uF capacitor, electrical cleaner.', 'labor_cost' => 450, 'materials_cost' => 320, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Fan returned to normal operation.', 'testing' => 'All three speeds and oscillation passed a 20-minute test.', 'technician_remarks' => 'Clean fan guard during scheduled maintenance.', 'priority' => 'Medium', 'report_type' => 'malfunction'],
            ['asset_index' => 80, 'title' => 'Service pickup engine difficult to start', 'problem' => 'Service pickup required several attempts to start.', 'damage' => 'Slow cranking and intermittent engine start.', 'findings' => 'Battery failed load test and terminals were corroded; alternator output normal.', 'defects' => 'Battery and terminal clamps.', 'cause' => 'Battery at end of service life.', 'action' => 'Replace battery and clean charging connections.', 'required_repairs' => 'Install correct battery and verify charging system.', 'work' => 'Installed a new battery, cleaned terminals, and checked alternator output and starter draw.', 'repaired' => 'Battery connections and charging terminals.', 'replaced' => 'Maintenance-free battery.', 'parts' => '12 V automotive battery, terminal protectant.', 'labor_cost' => 850, 'materials_cost' => 7200, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Vehicle returned to municipal service.', 'testing' => 'Cold start and charging checks passed; no warning lights observed.', 'technician_remarks' => 'Battery warranty information filed with vehicle records.', 'priority' => 'Urgent', 'report_type' => 'malfunction'],
            ['asset_index' => 22, 'title' => 'Filing cabinet drawer will not open', 'problem' => 'Top drawer jammed and would not open with the key.', 'damage' => 'Drawer slides bound and lock linkage was stiff.', 'findings' => 'Drawer was overloaded; slide rails were bent and lock needed lubrication.', 'defects' => 'Drawer slide rail pair.', 'cause' => 'Excessive drawer load and lack of lubrication.', 'action' => 'Replace slides, lubricate lock, and check cabinet stability.', 'required_repairs' => 'Fit slide rails and adjust drawer alignment.', 'work' => 'Removed drawer, replaced rails, lubricated the lock, and adjusted cabinet feet.', 'repaired' => 'Lock linkage and cabinet leveling.', 'replaced' => 'Drawer slide rail pair.', 'parts' => 'Heavy-duty drawer slides, lubricant.', 'labor_cost' => 550, 'materials_cost' => 1250, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Drawer restored to safe operation.', 'testing' => 'Drawer opened and locked smoothly through ten cycles.', 'technician_remarks' => 'Redistribute files to keep drawer load within capacity.', 'priority' => 'Low', 'report_type' => 'damage'],
            ['asset_index' => 60, 'title' => 'Handheld radio has weak transmission', 'problem' => 'Field radio transmission was intermittent at normal operating distance.', 'damage' => 'Weak transmit range and cracked antenna casing.', 'findings' => 'Antenna connector was loose and battery capacity was below specification.', 'defects' => 'Antenna and rechargeable battery pack.', 'cause' => 'Impact damage and battery aging.', 'action' => 'Replace antenna and battery; run radio range check.', 'required_repairs' => 'Install matched antenna and battery pack; verify channel programming.', 'work' => 'Replaced antenna and battery pack, checked channel settings, and completed a radio-to-base test.', 'repaired' => 'Antenna connection and channel programming.', 'replaced' => 'Antenna and battery pack.', 'parts' => 'Compatible VHF antenna, rechargeable battery pack.', 'labor_cost' => 500, 'materials_cost' => 3850, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Radio restored to operational condition.', 'testing' => 'Clear transmission and reception confirmed with base radio.', 'technician_remarks' => 'Return unit to the emergency response radio inventory.', 'priority' => 'High', 'report_type' => 'malfunction'],
            ['asset_index' => 5, 'title' => 'Network switch ports intermittently disconnect', 'problem' => 'Several office network connections dropped intermittently.', 'damage' => 'Link lights failed on four switch ports.', 'findings' => 'Ports 17-20 failed loopback test; remaining ports and power supply were stable.', 'defects' => 'Four-port switch module.', 'cause' => 'Port module failure after power fluctuation.', 'action' => 'Replace module and inspect surge protection.', 'required_repairs' => 'Replace port module and verify VLAN and uplink configuration.', 'work' => 'Replaced the port module, checked surge protection, and tested the affected office connections.', 'repaired' => 'Network configuration and uplink.', 'replaced' => 'Four-port switch module.', 'parts' => 'Managed switch replacement module.', 'labor_cost' => 1600, 'materials_cost' => 8900, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Office network connectivity restored.', 'testing' => 'All affected ports passed link and data-transfer tests.', 'technician_remarks' => 'Monitor for further power-related network interruptions.', 'priority' => 'High', 'report_type' => 'malfunction'],
            ['asset_index' => 100, 'title' => 'Blood pressure monitor gives inconsistent readings', 'problem' => 'Clinic monitor readings varied substantially between repeated checks.', 'damage' => 'Inconsistent pressure values and a worn cuff tube.', 'findings' => 'Cuff connector leaked air; reference check indicated sensor remained within tolerance after replacement.', 'defects' => 'Adult cuff and air tube.', 'cause' => 'Worn cuff tubing.', 'action' => 'Replace cuff and compare against a calibrated reference unit.', 'required_repairs' => 'Install new cuff and verify measurement repeatability.', 'work' => 'Replaced the cuff, cleaned the connector, and compared repeated readings with a reference monitor.', 'repaired' => 'Air connector and cuff fit.', 'replaced' => 'Adult cuff and tube.', 'parts' => 'Omron-compatible adult cuff assembly.', 'labor_cost' => 450, 'materials_cost' => 980, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Monitor cleared for clinical use.', 'testing' => 'Repeated readings were within the clinic reference tolerance.', 'technician_remarks' => 'Retain calibration check record at the health center.', 'priority' => 'High', 'report_type' => 'malfunction'],
            ['asset_index' => 81, 'title' => 'Ambulance battery warning and intermittent start', 'problem' => 'Ambulance dashboard battery warning appeared during vehicle start.', 'damage' => 'Slow cranking and inconsistent charging indication.', 'findings' => 'Battery failed load test; charging system output was normal.', 'defects' => 'Starting battery.', 'cause' => 'Battery service life expired.', 'action' => 'Replace battery and verify emergency accessory charging.', 'required_repairs' => 'Install correct battery and check alternator and accessory circuits.', 'work' => 'Replaced battery, cleaned terminals, and verified charging with emergency lights operating.', 'repaired' => 'Battery terminals and accessory circuit connections.', 'replaced' => 'Starting battery.', 'parts' => 'Heavy-duty 12 V battery, terminal covers.', 'labor_cost' => 900, 'materials_cost' => 8950, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Ambulance cleared for service.', 'testing' => 'Repeated start and charging tests passed with accessories on.', 'technician_remarks' => 'No fault found in the alternator circuit.', 'priority' => 'Urgent', 'report_type' => 'malfunction'],
            ['asset_index' => 123, 'title' => 'Generator fuel line leak', 'problem' => 'Standby generator showed a fuel smell and a small leak during inspection.', 'damage' => 'Fuel seepage at the hose connection.', 'findings' => 'Fuel hose had surface cracks; engine and alternator passed operational checks.', 'defects' => 'Fuel return hose and hose clamps.', 'cause' => 'Hose material deterioration.', 'action' => 'Replace hose, clean fuel residue, and load-test generator.', 'required_repairs' => 'Fit fuel-rated hose and clamps; conduct leak and load tests.', 'work' => 'Replaced fuel hose and clamps, cleaned the housing, and ran generator under supervised load.', 'repaired' => 'Fuel connection and housing.', 'replaced' => 'Fuel return hose and clamps.', 'parts' => 'Fuel-rated hose, stainless clamps, absorbent pads.', 'labor_cost' => 1200, 'materials_cost' => 1850, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Leak eliminated and generator serviceable.', 'testing' => 'No fuel seepage after 40-minute load test.', 'technician_remarks' => 'Keep the unit on the monthly generator inspection schedule.', 'priority' => 'Urgent', 'report_type' => 'damage'],
            ['asset_index' => 140, 'title' => 'Grass cutter engine sputters under load', 'problem' => 'Grass cutter stalled when cutting dense grass.', 'damage' => 'Engine sputtered and lost power under load.', 'findings' => 'Air filter was clogged and spark plug was worn; carburetor passed cleaning test.', 'defects' => 'Air filter and spark plug.', 'cause' => 'Dust and normal engine wear.', 'action' => 'Replace filter and plug; clean carburetor and test under load.', 'required_repairs' => 'Install service kit and adjust idle speed.', 'work' => 'Replaced filter and spark plug, cleaned carburetor, and tested cutter on municipal grounds.', 'repaired' => 'Carburetor and idle adjustment.', 'replaced' => 'Air filter and spark plug.', 'parts' => 'Engine service kit, fuel filter, spark plug.', 'labor_cost' => 850, 'materials_cost' => 1250, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Equipment returned to maintenance crew.', 'testing' => 'Started reliably and cut grass under normal load.', 'technician_remarks' => 'Use clean fuel and follow the service interval.', 'priority' => 'Medium', 'report_type' => 'malfunction'],
            ['asset_index' => 160, 'title' => 'Basketball goal backboard is loose', 'problem' => 'Court basketball backboard moved during normal play.', 'damage' => 'Mounting bolts loosened and one brace was bent.', 'findings' => 'Two anchors were loose; backboard had no structural crack.', 'defects' => 'Mounting anchors and support brace.', 'cause' => 'Repeated vibration and weather exposure.', 'action' => 'Replace anchors and brace; inspect the full assembly.', 'required_repairs' => 'Secure frame with rated anchors and verify backboard stability.', 'work' => 'Replaced anchors and brace, tightened the frame, and checked hoop alignment.', 'repaired' => 'Goal support frame.', 'replaced' => 'Two anchors and one steel brace.', 'parts' => 'Rated expansion anchors, steel brace, corrosion-resistant bolts.', 'labor_cost' => 1500, 'materials_cost' => 2850, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Court equipment safe for use.', 'testing' => 'Backboard and rim remained stable during supervised load test.', 'technician_remarks' => 'Inspect anchors before scheduled sports events.', 'priority' => 'High', 'report_type' => 'damage'],
            ['asset_index' => 180, 'title' => 'Municipal Hall roof leaking near records room', 'problem' => 'Rainwater entered the records area during heavy rain.', 'damage' => 'Roof leak and water staining on the ceiling.', 'findings' => 'Roof flashing had separated; structural framing remained sound.', 'defects' => 'Roof flashing and sealant.', 'cause' => 'Weather exposure and degraded sealant.', 'action' => 'Replace flashing and reseal roof joint.', 'required_repairs' => 'Install flashing, reseal seam, and monitor after rainfall.', 'work' => 'Replaced flashing and sealant, cleared the gutter, and inspected the affected ceiling.', 'repaired' => 'Roof joint and drainage channel.', 'replaced' => 'Metal flashing and weatherproof sealant.', 'parts' => 'Galvanized flashing, roof sealant, fasteners.', 'labor_cost' => 4200, 'materials_cost' => 3850, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Fair', 'final_status' => 'In Use', 'result' => 'Leak repaired; monitor during next rainfall.', 'testing' => 'Water test showed no leak at the repaired joint.', 'technician_remarks' => 'Protect nearby records until the ceiling has dried.', 'priority' => 'Urgent', 'report_type' => 'damage'],
            ['asset_index' => 42, 'title' => 'Document scanner jams at the feeder', 'problem' => 'Scanner stopped when multiple pages were loaded.', 'damage' => 'Paper jam at the automatic document feeder.', 'findings' => 'Feed rollers were glazed and separator pad was worn.', 'defects' => 'Feed roller set and separator pad.', 'cause' => 'Normal wear from high-volume scanning.', 'action' => 'Replace rollers and pad, then run a document feed test.', 'required_repairs' => 'Install feeder maintenance kit and clean sensor path.', 'work' => 'Replaced feed rollers and separator pad, cleaned sensors, and tested mixed-size documents.', 'repaired' => 'Feeder sensor path.', 'replaced' => 'Feed roller set and separator pad.', 'parts' => 'Scanner feeder maintenance kit.', 'labor_cost' => 750, 'materials_cost' => 2480, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Feeder operation restored.', 'testing' => 'Fifty-page duplex test completed without a jam.', 'technician_remarks' => 'Remove staples before scanning records.', 'priority' => 'Medium', 'report_type' => 'malfunction'],
            ['asset_index' => 3, 'title' => 'UPS battery no longer holds charge', 'problem' => 'UPS shut down immediately during a brief power interruption.', 'damage' => 'Backup runtime reduced to less than one minute.', 'findings' => 'Battery pack failed capacity test; inverter passed.', 'defects' => 'Sealed lead-acid battery pack.', 'cause' => 'Battery reached end of expected service life.', 'action' => 'Replace battery pack and perform a controlled load test.', 'required_repairs' => 'Install compatible battery and check transfer operation.', 'work' => 'Replaced the battery pack, cleaned terminals, and completed a controlled load test.', 'repaired' => 'Battery connections.', 'replaced' => 'UPS battery pack.', 'parts' => 'Compatible 12 V battery pack.', 'labor_cost' => 500, 'materials_cost' => 2450, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Backup power restored.', 'testing' => 'Unit transferred to battery and held the test load for 18 minutes.', 'technician_remarks' => 'Record battery replacement date on service schedule.', 'priority' => 'High', 'report_type' => 'malfunction'],
            ['asset_index' => 44, 'title' => 'Photocopier producing streaked copies', 'problem' => 'Copies had dark vertical streaks and uneven density.', 'damage' => 'Streaks appeared on every page.', 'findings' => 'Drum surface was scratched and transfer roller was contaminated.', 'defects' => 'Imaging drum and transfer roller.', 'cause' => 'Consumable wear and accumulated paper dust.', 'action' => 'Replace drum and clean transfer assembly.', 'required_repairs' => 'Install imaging unit and calibrate copy density.', 'work' => 'Replaced imaging unit, cleaned transfer path, and calibrated copy settings.', 'repaired' => 'Transfer path and density calibration.', 'replaced' => 'Imaging drum unit.', 'parts' => 'Compatible imaging unit, cleaning kit.', 'labor_cost' => 950, 'materials_cost' => 8900, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Copy quality restored.', 'testing' => 'Twenty copy samples passed visual quality inspection.', 'technician_remarks' => 'Track drum life with the monthly meter reading.', 'priority' => 'Medium', 'report_type' => 'damage'],
            ['asset_index' => 61, 'title' => 'Base radio audio cuts out', 'problem' => 'Emergency operations base radio audio cut out intermittently.', 'damage' => 'Intermittent reception and static noise.', 'findings' => 'Antenna coax connector had corrosion; receiver passed bench test.', 'defects' => 'Coax connector and grounding strap.', 'cause' => 'Moisture ingress at the antenna connection.', 'action' => 'Replace connector, reseal entry point, and verify grounding.', 'required_repairs' => 'Fit weatherproof connector and test base-to-field communications.', 'work' => 'Replaced connector and grounding strap, resealed the entry, and tested with handheld units.', 'repaired' => 'Antenna feed and grounding.', 'replaced' => 'Coax connector and grounding strap.', 'parts' => 'Weatherproof coax connector, grounding strap, sealant.', 'labor_cost' => 1100, 'materials_cost' => 1750, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Base radio operation stabilized.', 'testing' => 'Clear two-way audio confirmed with three field radios.', 'technician_remarks' => 'Inspect antenna seal during quarterly checks.', 'priority' => 'Urgent', 'report_type' => 'malfunction'],
            ['asset_index' => 124, 'title' => 'Water pump leaking at the seal', 'problem' => 'Water pump leaked from the shaft area during operation.', 'damage' => 'Visible water seepage and vibration.', 'findings' => 'Mechanical seal was worn; bearings remained within service tolerance.', 'defects' => 'Mechanical seal and gasket.', 'cause' => 'Seal wear after extended pumping.', 'action' => 'Replace seal and gasket; align pump and motor.', 'required_repairs' => 'Install seal kit and confirm leak-free operation.', 'work' => 'Replaced mechanical seal and gasket, aligned coupling, and ran a pressure test.', 'repaired' => 'Pump coupling alignment.', 'replaced' => 'Mechanical seal and gasket.', 'parts' => 'Pump seal kit, gasket, thread sealant.', 'labor_cost' => 1600, 'materials_cost' => 2450, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Pump returned to service.', 'testing' => 'No leaks during 45-minute pressure and flow test.', 'technician_remarks' => 'Check strainer monthly to reduce seal wear.', 'priority' => 'High', 'report_type' => 'damage'],
            ['asset_index' => 141, 'title' => 'Welding machine arc is unstable', 'problem' => 'Welding arc fluctuated during light fabrication work.', 'damage' => 'Unstable arc and intermittent output.', 'findings' => 'Electrode holder cable had a damaged conductor; machine output was stable on test leads.', 'defects' => 'Electrode cable and holder.', 'cause' => 'Cable fatigue from repeated bending.', 'action' => 'Replace cable and holder; test current settings.', 'required_repairs' => 'Fit welding cable and inspect earth clamp.', 'work' => 'Replaced electrode cable and holder, cleaned earth clamp, and verified output at multiple settings.', 'repaired' => 'Earth clamp connection.', 'replaced' => 'Electrode cable and holder.', 'parts' => 'Welding cable, insulated electrode holder.', 'labor_cost' => 950, 'materials_cost' => 2200, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Stable welding output restored.', 'testing' => 'Arc remained stable during a supervised test weld.', 'technician_remarks' => 'Store cables loosely coiled to prevent conductor fatigue.', 'priority' => 'Medium', 'report_type' => 'malfunction'],
            ['asset_index' => 161, 'title' => 'Volleyball post is unstable', 'problem' => 'Volleyball post shifted during net adjustment.', 'damage' => 'Base plate moved and anchor bolts were loose.', 'findings' => 'Two anchors had pulled partially from the concrete; post was not bent.', 'defects' => 'Concrete anchors and base plate fasteners.', 'cause' => 'Repeated lateral loading and loosened anchors.', 'action' => 'Replace anchors and verify post plumb and stability.', 'required_repairs' => 'Install rated anchors and secure the base plate.', 'work' => 'Installed new anchors, tightened base plate, and checked post alignment.', 'repaired' => 'Post alignment and base plate.', 'replaced' => 'Concrete anchors and fasteners.', 'parts' => 'Rated concrete anchors, galvanized nuts and washers.', 'labor_cost' => 1250, 'materials_cost' => 1650, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'Available', 'result' => 'Sports equipment safe for scheduled use.', 'testing' => 'Post remained stable during supervised net-tension check.', 'technician_remarks' => 'Restrict use until anchor checks are completed after events.', 'priority' => 'High', 'report_type' => 'damage'],
            ['asset_index' => 181, 'title' => 'Health center building has a leaking washroom pipe', 'problem' => 'Water leaked from the clinic washroom wall and affected the adjacent room.', 'damage' => 'Persistent leak at concealed pipe joint.', 'findings' => 'Galvanized branch pipe was corroded; surrounding wall required patching.', 'defects' => 'Pipe section, elbow joint and shutoff valve.', 'cause' => 'Corrosion and aging plumbing.', 'action' => 'Replace pipe section and valve; repair wall after leak test.', 'required_repairs' => 'Install PVC pressure pipe and fittings; patch affected wall.', 'work' => 'Replaced corroded pipe and valve, pressure-tested the line, and patched the wall finish.', 'repaired' => 'Washroom plumbing and wall finish.', 'replaced' => 'Pipe section, elbow and shutoff valve.', 'parts' => 'PVC pressure pipe, fittings, valve, patching compound.', 'labor_cost' => 4800, 'materials_cost' => 3250, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Fair', 'final_status' => 'In Use', 'result' => 'Leak repaired; facility returned to use.', 'testing' => 'Pressure held without seepage for one hour.', 'technician_remarks' => 'Schedule follow-up visual check after one week.', 'priority' => 'Urgent', 'report_type' => 'damage'],
            ['asset_index' => 4, 'title' => 'Server overheating alarm', 'problem' => 'Server logged a high-temperature alarm during afternoon operation.', 'damage' => 'Elevated CPU temperature and intermittent fan noise.', 'findings' => 'One chassis fan bearing was noisy and intake filter was blocked.', 'defects' => 'Chassis fan and intake filter.', 'cause' => 'Dust accumulation and fan bearing wear.', 'action' => 'Replace fan, clean intake, and monitor server temperature.', 'required_repairs' => 'Install compatible fan and verify server cooling under load.', 'work' => 'Replaced chassis fan, cleaned filters, and ran a monitored service load.', 'repaired' => 'Cooling airflow path.', 'replaced' => 'Chassis fan and intake filter.', 'parts' => 'Server fan module, intake filter.', 'labor_cost' => 1250, 'materials_cost' => 4850, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Server returned to normal operating temperature.', 'testing' => 'Temperature remained within manufacturer limits during a two-hour load test.', 'technician_remarks' => 'Keep the server-room intake area free of stored items.', 'priority' => 'Urgent', 'report_type' => 'malfunction'],
            ['asset_index' => 6, 'title' => 'Desktop computer has a failed power supply', 'problem' => 'Desktop restarted repeatedly when staff opened large files.', 'damage' => 'Unexpected shutdowns under processor load.', 'findings' => 'Power supply output fluctuated; motherboard and storage passed diagnostics.', 'defects' => 'Power supply unit.', 'cause' => 'Power supply component failure.', 'action' => 'Replace power supply and run full hardware diagnostics.', 'required_repairs' => 'Install compatible power supply and verify system stability.', 'work' => 'Replaced the power supply and ran memory, storage, and operating system checks.', 'repaired' => 'Power connections and cable routing.', 'replaced' => 'Power supply unit.', 'parts' => 'Compatible 550 W power supply.', 'labor_cost' => 700, 'materials_cost' => 3150, 'initial_condition' => 'Poor', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Good', 'final_status' => 'In Use', 'result' => 'Unit repaired and returned to service.', 'testing' => 'Passed repeated restarts and a one-hour system test.', 'technician_remarks' => 'No further hardware faults observed.', 'priority' => 'High', 'report_type' => 'malfunction'],
            ['asset_index' => 83, 'title' => 'Service vehicle braking system is unsafe', 'problem' => 'Vehicle produced grinding noise and reduced braking response.', 'damage' => 'Brake pads worn beyond safe service thickness.', 'findings' => 'Rotor scoring exceeded safe resurfacing allowance; caliper body was corroded and seized.', 'defects' => 'Brake pads, rotors and seized caliper.', 'cause' => 'Advanced wear and corrosion; safe repair requires major replacement.', 'action' => 'Do not return vehicle to service; recommend disposal and replacement.', 'required_repairs' => 'Major brake system replacement is not cost-effective for this unit.', 'work' => 'Completed safety inspection, isolated the vehicle, and documented the unserviceable brake assembly for custodian review.', 'repaired' => 'No repair performed; vehicle isolated.', 'replaced' => 'None; unit recommended for disposal.', 'parts' => 'None.', 'labor_cost' => 850, 'materials_cost' => 0, 'initial_condition' => 'Unserviceable', 'initial_status' => 'Under Maintenance', 'final_condition' => 'Unserviceable', 'final_status' => 'Disposed', 'result' => 'Not economically repairable; custodian-approved disposal recorded in sample data.', 'testing' => 'Not road-tested; vehicle remained isolated for safety.', 'technician_remarks' => 'Do not operate. This is a sample unrepairable-unit history.', 'priority' => 'Urgent', 'report_type' => 'damage'],
        ];

        return $items;
    }
}
