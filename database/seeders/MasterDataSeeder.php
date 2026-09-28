<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Eloquent Models (as per your project)
use App\Models\Admin\Branch;
use App\Models\Admin\Location;
use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Designation;
use App\Models\Admin\Vertical;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Starting Master Data Seeder (Eloquent Models)...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $this->truncateTables();

        $this->seedBranches();
        $this->seedLocations();
        $this->seedVerticals();
        $this->seedSegments();
        $this->seedSubSegments();
        $this->seedDepartments();
        $this->seedDivisions();
        $this->seedDesignations();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('Master Data Seeder completed successfully!');
    }

    private function truncateTables(): void
    {
        // Only truncate tables that actually exist
        $models = [
            Branch::class,
            Location::class,
            Vertical::class,
            Segment::class,
            SubSegment::class,
            Department::class,
            Division::class,
            Designation::class,
        ];

        foreach ($models as $model) {
            $table = (new $model)->getTable();
            DB::table($table)->truncate();
            $this->command->info("Truncated: {$table}");
        }
    }

    // ==================== BRANCH ====================
    private function seedBranches(): void
    {
        $data = [
            ['code' => 'BKN', 'name' => 'Bikaner',   'city' => 'Bikaner',   'state' => 'Rajasthan', 'is_head_office' => true,  'is_active' => true],
            ['code' => 'CHR', 'name' => 'Churu',     'city' => 'Churu',     'state' => 'Rajasthan', 'is_head_office' => false, 'is_active' => true],
            ['code' => 'SUJ', 'name' => 'Sujangarh', 'city' => 'Sujangarh', 'state' => 'Rajasthan', 'is_head_office' => false, 'is_active' => true],
        ];

        Branch::insert($data);
        $this->command->info('Seeded: Branches');
    }

    // ==================== LOCATION ====================
    private function seedLocations(): void
    {
        $data = [
            ['code' => 'BKN', 'name' => 'Bikaner', 'branch_code' => 'BKN', 'is_sales_location' => true, 'is_workshop' => true, 'is_parts_location' => true, 'is_stock_location' => true, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => 28.023836, 'longitude' => 73.382668, 'is_active' => true],
            ['code' => 'CHR', 'name' => 'Churu', 'branch_code' => 'CHR', 'is_sales_location' => true, 'is_workshop' => true, 'is_parts_location' => true, 'is_stock_location' => true, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => 28.294001, 'longitude' => 74.920456, 'is_active' => true],
            ['code' => 'KOL', 'name' => 'KOLAYAT', 'branch_code' => 'BKN', 'is_sales_location' => true, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => null, 'longitude' => null, 'is_active' => true],
            ['code' => 'NOK', 'name' => 'NOKHA', 'branch_code' => 'BKN', 'is_sales_location' => true, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => 26.885211, 'longitude' => 75.7905578, 'is_active' => true],
            ['code' => 'SUJ', 'name' => 'SUJANGARH', 'branch_code' => 'SUJ', 'is_sales_location' => true, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => 27.750978, 'longitude' => 74.455551, 'is_active' => true],
            ['code' => 'LNK', 'name' => 'LUNKARANSAR', 'branch_code' => 'BKN', 'is_sales_location' => true, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => 28.48163981, 'longitude' => 73.74695972, 'is_active' => true],
            ['code' => 'DNG', 'name' => 'SRI DUNGARGARH', 'branch_code' => 'BKN', 'is_sales_location' => true, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => 28.089452, 'longitude' => 73.997964, 'is_active' => true],
            ['code' => 'KJW', 'name' => 'KHAJUWALA', 'branch_code' => 'BKN', 'is_sales_location' => true, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => null, 'longitude' => null, 'is_active' => true],
            ['code' => 'RTN', 'name' => 'RATANGARH', 'branch_code' => 'CHR', 'is_sales_location' => true, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => 28.060365, 'longitude' => 74.3559, 'is_active' => true],
            ['code' => 'RJG', 'name' => 'RAJGARH', 'branch_code' => 'CHR', 'is_sales_location' => true, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => 28.6456837, 'longitude' => 75.3724018, 'is_active' => true],
            ['code' => 'SDR', 'name' => 'SARDARSHAHAR', 'branch_code' => 'CHR', 'is_sales_location' => true, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => 28.4322121, 'longitude' => 74.5006099, 'is_active' => true],
            ['code' => 'TRN', 'name' => 'TARANAGAR', 'branch_code' => 'CHR', 'is_sales_location' => true, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => null, 'longitude' => null, 'is_active' => true],
            ['code' => 'CTG', 'name' => 'CHHATARGARH', 'branch_code' => 'BKN', 'is_sales_location' => true, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => null, 'longitude' => null, 'is_active' => true],
            ['code' => 'MWH', 'name' => 'MOTHER WAREHOUSE', 'branch_code' => 'BKN', 'is_sales_location' => false, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => true, 'is_lmmws' => false, 'latitude' => 28.0451871, 'longitude' => 73.4533589, 'is_active' => true],
            ['code' => 'LMM-WS', 'name' => 'LMM Workshop', 'branch_code' => 'BKN', 'is_sales_location' => false, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => false, 'is_mwh' => false, 'is_lmmws' => true, 'latitude' => 28.032291, 'longitude' => 73.406532, 'is_active' => true],
            ['code' => 'GGN', 'name' => 'Gurugram', 'branch_code' => 'BKN', 'is_sales_location' => false, 'is_workshop' => false, 'is_parts_location' => false, 'is_stock_location' => false, 'is_office_only' => true, 'is_mwh' => false, 'is_lmmws' => false, 'latitude' => 28.414652, 'longitude' => 77.095055, 'is_active' => true],
        ];

        Location::insert($data);
        $this->command->info('Seeded: Locations');
    }

    // ==================== VERTICAL ====================
    private function seedVerticals(): void
    {
        $data = [
            ['code' => 'UC', 'name' => 'USED CAR', 'is_active' => true],
            ['code' => 'NC', 'name' => 'NEW CAR',  'is_active' => true],
        ];

        Vertical::insert($data);
        $this->command->info('Seeded: Verticals');
    }

    // ==================== SEGMENT ====================
    private function seedSegments(): void
    {
        $data = [
            ['code' => 'PV',  'name' => 'PERSONAL',   'is_active' => true],
            ['code' => 'CV',  'name' => 'COMMERCIAL', 'is_active' => true],
            ['code' => 'BEV', 'name' => 'BEV',        'is_active' => true],
            ['code' => 'LMM', 'name' => 'LMM',        'is_active' => true],
        ];

        Segment::insert($data);
        $this->command->info('Seeded: Segments');
    }

    // ==================== SUB SEGMENT ====================
    private function seedSubSegments(): void
    {
        $data = [
            ['code' => 'XUV',  'name' => 'XUV',        'segment_code' => 'PV',  'is_active' => true],
            ['code' => 'NXUV', 'name' => 'NXUV',       'segment_code' => 'PV',  'is_active' => true],
            ['code' => 'PV',   'name' => 'PERSONAL',   'segment_code' => 'PV',  'is_active' => true],
            ['code' => 'CV',   'name' => 'COMMERCIAL', 'segment_code' => 'CV',  'is_active' => true],
            ['code' => 'BEV',  'name' => 'BEV',        'segment_code' => 'BEV', 'is_active' => true],
            ['code' => 'LMM',  'name' => 'LMM',        'segment_code' => 'LMM', 'is_active' => true],
        ];

        SubSegment::insert($data);
        $this->command->info('Seeded: SubSegments');
    }

    // ==================== DEPARTMENT ====================
    private function seedDepartments(): void
    {
        $data = [
            ['code' => 'ACC', 'name' => 'Accounts', 'is_active' => true],
            ['code' => 'ADM', 'name' => 'Admin',    'is_active' => true],
            ['code' => 'HR',  'name' => 'HR',       'is_active' => true],
            ['code' => 'INS', 'name' => 'Insurance','is_active' => true],
            ['code' => 'SLS', 'name' => 'Sales',    'is_active' => true],
            ['code' => 'SRV', 'name' => 'Service',  'is_active' => true],
        ];

        Department::insert($data);
        $this->command->info('Seeded: Departments');
    }

    // ==================== DIVISION ====================
    private function seedDivisions(): void
    {
        $data = [
            ['code' => 'ACC', 'name' => 'Accounts', 'dept_code' => 'ACC', 'is_active' => true],
            ['code' => 'ADM', 'name' => 'Admin', 'dept_code' => 'ADM', 'is_active' => true],
            ['code' => 'INF', 'name' => 'Infra', 'dept_code' => 'ADM', 'is_active' => true],
            ['code' => 'IT', 'name' => 'IT', 'dept_code' => 'ADM', 'is_active' => true],
            ['code' => 'PRSNL', 'name' => 'Personal', 'dept_code' => 'ADM', 'is_active' => true],
            ['code' => 'ASC', 'name' => 'Associates', 'dept_code' => 'ASC', 'is_active' => true],
            ['code' => 'HR', 'name' => 'HR', 'dept_code' => 'HR', 'is_active' => true],
            ['code' => 'INS', 'name' => 'Insurance', 'dept_code' => 'INS', 'is_active' => true],
            ['code' => 'LMM', 'name' => 'LMM', 'dept_code' => 'SLS', 'is_active' => true],
            ['code' => 'COMML', 'name' => 'Commercial', 'dept_code' => 'SLS', 'is_active' => true],
            ['code' => 'BO', 'name' => 'Back Office', 'dept_code' => 'SLS', 'is_active' => true],
            ['code' => 'SLS', 'name' => 'Sales', 'dept_code' => 'SLS', 'is_active' => true],
            ['code' => 'VFN', 'name' => 'Vehicle Finance', 'dept_code' => 'SLS', 'is_active' => true],
            ['code' => 'CR', 'name' => 'CRM', 'dept_code' => 'SLS', 'is_active' => true],
            ['code' => 'UCAR', 'name' => 'Used Car', 'dept_code' => 'SLS', 'is_active' => true],
            ['code' => 'MECH_P', 'name' => 'Mechanical Personal', 'dept_code' => 'SRV', 'is_active' => true],
            ['code' => 'SPARE', 'name' => 'Spare Parts', 'dept_code' => 'SRV', 'is_active' => true],
            ['code' => 'MECH_C', 'name' => 'Mechanical Commercial', 'dept_code' => 'SRV', 'is_active' => true],
            ['code' => 'MECH_L', 'name' => 'Mechanical LMM', 'dept_code' => 'SRV', 'is_active' => true],
            ['code' => 'CR_S', 'name' => 'CRM', 'dept_code' => 'SRV', 'is_active' => true],
            ['code' => 'SRV', 'name' => 'Service', 'dept_code' => 'SRV', 'is_active' => true],
            ['code' => 'BDSHP', 'name' => 'Bodyshop', 'dept_code' => 'SRV', 'is_active' => true],
            ['code' => 'ACCE', 'name' => 'Accessories', 'dept_code' => 'SRV', 'is_active' => true],
            ['code' => 'PDI', 'name' => 'PDI', 'dept_code' => 'SRV', 'is_active' => true],
            ['code' => 'WASH', 'name' => 'Washing', 'dept_code' => 'SRV', 'is_active' => true],
        ];

        Division::insert($data);
        $this->command->info('Seeded: Divisions');
    }

    // ==================== DESIGNATION ====================
    private function seedDesignations(): void
    {
        $data = [
            ['code' => 'ACC_EXE', 'name' => 'Accessories Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'ACC_FTR', 'name' => 'Accessories Fitter', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'ACS_EXE', 'name' => 'Accounts Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'ACS_MGR', 'name' => 'Accounts Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'ADM_EXE', 'name' => 'Admin Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'ADM_MGR', 'name' => 'Admin Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'AST_ACC_MGR', 'name' => 'Assistant Accounts Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'AST_ADM_MGR', 'name' => 'Assistant Admin Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'AST_BSP_MGR', 'name' => 'Assistant Bodyshop Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'AST_INF_MGR', 'name' => 'Assistant Infra Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'BO_EXE', 'name' => 'Back Office Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'BO_MGR', 'name' => 'Back Office Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'BSP_MGR', 'name' => 'Bodyshop Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'BR_MGR', 'name' => 'Branch Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'BH_SRV', 'name' => 'Business Head - Service', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'CEO', 'name' => 'CEO', 'is_top_mgmt' => true, 'is_active' => true],
            ['code' => 'CXM', 'name' => 'Customer Experience Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'CRE', 'name' => 'Customer Relationship Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'CRM', 'name' => 'Customer Relationship Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'DGM', 'name' => 'Deputy General Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'DEM', 'name' => 'Digital Marketing Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'DRCT', 'name' => 'Director', 'is_top_mgmt' => true, 'is_active' => true],
            ['code' => 'DRVR', 'name' => 'Driver', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'EDP_EXE', 'name' => 'EDP Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'ELEC', 'name' => 'Electrician', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'FACT_MGR', 'name' => 'Factory Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'FLR_CTRL', 'name' => 'Floor Controller', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'FLR_INCH', 'name' => 'Floor Incharge', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'GEN_MGR', 'name' => 'General Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'HOST', 'name' => 'Hostess', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'HKP_EXE', 'name' => 'Housekeeping Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'HR_EXE', 'name' => 'HR Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'HR_MGR', 'name' => 'HR Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'HYG_SUP', 'name' => 'Hygiene Supervisor', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'INFR_EXE', 'name' => 'Infra Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'INS_ADV_FLD', 'name' => 'Insurance Advisor - Field', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'INS_ADV_TEL', 'name' => 'Insurance Advisor - Tele', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'INS_COORD', 'name' => 'Insurance Coordinator', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'INS_MGR', 'name' => 'Insurance Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'INT_AUD', 'name' => 'Internal Auditor', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'LOG_COORD', 'name' => 'Logistics Coordinator', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'MIS_EXE', 'name' => 'MIS Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'OFF_BOY', 'name' => 'Office Boy', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'PRT_EXE', 'name' => 'Parts Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'PRT_MGR', 'name' => 'Parts Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'PDI_EXE', 'name' => 'PDI Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'PDI_INCH', 'name' => 'PDI Incharge', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'PRJ_INCH', 'name' => 'Project Incharge', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'PUR_MGR', 'name' => 'Purchase Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'QLT_CTRL', 'name' => 'Quality Controller', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'RECP', 'name' => 'Receptionist', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'SLS_CSH', 'name' => 'Sales Cashier', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'SLS_CONS', 'name' => 'Sales Consultant', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'SLS_MGR', 'name' => 'Sales Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'SEC_GUARD', 'name' => 'Security Guard', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'SRV_ADV', 'name' => 'Service Advisor', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'SRV_CSH', 'name' => 'Service Cashier', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'SRV_MGR', 'name' => 'Service Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'TECH_MGR', 'name' => 'Technical Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'TECH_MGR_TRN', 'name' => 'Technical Manager - Trainee', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'TECH', 'name' => 'Technician', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'TECH_TRN', 'name' => 'Technician - Trainee', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'TD_EXE', 'name' => 'Test Drive Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'TOOL_INCH', 'name' => 'Tool Incharge', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'UCAR_MGR', 'name' => 'Used Car Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'UCAR_PRC_MGR', 'name' => 'Used Car Procurement Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'UCAR_RFRB_EXE', 'name' => 'Used Car Refurb Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'UCAR_SLS_CONS', 'name' => 'Used Car Sales Consultant', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'VFN_EXE', 'name' => 'Vehicle Finance Executive', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'VFN_MGR', 'name' => 'Vehicle Finance Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'WAR_AST', 'name' => 'Warranty Assistant', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'WAR_MGR', 'name' => 'Warranty Manager', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'WASH_BOY', 'name' => 'Washing Boy', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'WEB_DEV', 'name' => 'Web Developer', 'is_top_mgmt' => false, 'is_active' => true],
            ['code' => 'WEBDEV_INT', 'name' => 'Web Developer - Intern', 'is_top_mgmt' => false, 'is_active' => true],
        ];

        Designation::insert($data);
        $this->command->info('Seeded: Designations ('.count($data).')');
    }
}