<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Covers Site Planning's Plans/Drawing checklist and Monitoring's Infrastructure Deployment
 * Status checklist — both share the same shape (name/weight/section/subsection, varying by
 * design_plan_type), discriminated here by `context`. Data below is transcribed verbatim from
 * the previously-hardcoded App\Support\SitePlanningTaskDefinitions and
 * App\Support\StageTaskDefinitions, which now read from this table instead (see those classes).
 * `design_plan_type = null` means "common to every type within that context" — only Site
 * Planning's Initial Draft/Final Checking sections use this; Monitoring has no shared rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('context'); // 'site_planning' | 'monitoring'
            $table->string('design_plan_type')->nullable();
            $table->string('section')->nullable();
            $table->string('subsection')->nullable();
            $table->string('name');
            $table->decimal('weight', 5, 2)->unsigned()->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->seedSitePlanningTemplates();
        $this->seedMonitoringTemplates();
    }

    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }

    private function seedSitePlanningTemplates(): void
    {
        $initialDraft = [
            ['section' => 'Initial Draft', 'name' => 'Site Information & Remarks', 'weight' => 2.00],
            ['section' => 'Initial Draft', 'name' => 'Photos', 'weight' => 2.00],
            ['section' => 'Initial Draft', 'subsection' => 'Cabling Details', 'name' => 'Backbone', 'weight' => 1.50],
            ['section' => 'Initial Draft', 'subsection' => 'Cabling Details', 'name' => 'Backhaul', 'weight' => 1.50],
            ['section' => 'Initial Draft', 'subsection' => 'Cabling Details', 'name' => 'Distribution', 'weight' => 1.50],
            ['section' => 'Initial Draft', 'subsection' => 'Cabling Details', 'name' => 'Electrical', 'weight' => 1.50],
            ['section' => 'Initial Draft', 'name' => 'Equipment Location/Details & Vertical Diagram (MDF, SMDF, 3U, LPP/NAP & UPS)', 'weight' => 2.50],
            ['section' => 'Initial Draft', 'name' => 'Floor Plan', 'weight' => 2.50],
        ];

        $finalChecking = [
            ['section' => 'Final Checking', 'name' => 'Completed the Design Plan', 'weight' => 7.50],
            ['section' => 'Final Checking', 'name' => 'Forwarded it to the Project Manager (PM) for review', 'weight' => 7.50],
        ];

        $fiber = [
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Cover Page', 'name' => 'General Notes, Legend & Vicinity Map', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Cover Page', 'name' => 'Site Development Plan', 'weight' => 4.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Cover Page', 'name' => 'Building Details', 'weight' => 4.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Cover Page', 'name' => 'Standard Attachments', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Backbone and Backhaul', 'name' => 'Backbone Cabling with photo location', 'weight' => 9.50],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Backbone and Backhaul', 'name' => 'Backhaul Cabling with photo location', 'weight' => 9.50],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Backbone and Backhaul', 'name' => 'FOC Termination Layout', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Data Distribution Cabling Plan', 'name' => 'Network Block Diagram', 'weight' => 5.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Data Distribution Cabling Plan', 'name' => 'Vertical Riser Diagram', 'weight' => 5.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Data Distribution Cabling Plan', 'name' => 'Distribution Cabling: LCP & NAP', 'weight' => 5.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Data Distribution Cabling Plan', 'name' => 'Schedule of Data Equipments', 'weight' => 2.50],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Data Distribution Cabling Plan', 'name' => 'List of Data Equipments', 'weight' => 2.50],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Electrical Plan', 'name' => 'General Notes, Legend & Schedule of Load Calculation', 'weight' => 2.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Electrical Plan', 'name' => 'UPS Inverter Diagram', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Electrical Plan', 'name' => 'UPS Inverter Cabling with photo location', 'weight' => 10.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Electrical Plan', 'name' => 'Electrical Standard Attachment', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Electrical Plan', 'name' => 'Schedule of Loads & Calculation', 'weight' => 2.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Electrical Plan', 'name' => 'Schedule of Electrical Equipments', 'weight' => 2.00],
            ['section' => 'In Progress Design Plan - Fiber', 'subsection' => 'Electrical Plan', 'name' => 'List of Electrical Equipments', 'weight' => 2.00],
        ];

        $utpCopper = [
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Cover Page', 'name' => 'General Notes, Legend & Vicinity Map', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Cover Page', 'name' => 'Site Development Plan', 'weight' => 4.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Cover Page', 'name' => 'Building Details', 'weight' => 4.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Cover Page', 'name' => 'Standard Attachments', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Backbone and Backhaul', 'name' => 'Backbone Cabling with photo location', 'weight' => 10.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Backbone and Backhaul', 'name' => 'Backhaul Cabling with photo location', 'weight' => 10.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Data Distribution Cabling Plan', 'name' => 'Network Block Diagram', 'weight' => 5.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Data Distribution Cabling Plan', 'name' => 'Vertical Riser Diagram', 'weight' => 5.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Data Distribution Cabling Plan', 'name' => 'Distribution Cabling: SMDF & IDF photo location', 'weight' => 5.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Data Distribution Cabling Plan', 'name' => 'Schedule of Data Equipments', 'weight' => 2.50],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Data Distribution Cabling Plan', 'name' => 'List of Data Equipments', 'weight' => 2.50],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Electrical Plan', 'name' => 'General Notes, Legend & Schedule of Load Calculation', 'weight' => 3.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Electrical Plan', 'name' => 'UPS Inverter Diagram', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Electrical Plan', 'name' => 'UPS Inverter Cabling with photo location', 'weight' => 2.75],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Electrical Plan', 'name' => 'Electrical Vertical Riser', 'weight' => 2.75],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Electrical Plan', 'name' => 'Electrical SMDF equipment cabling photo location', 'weight' => 2.75],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Electrical Plan', 'name' => 'Electrical IDF equipment cabling photo location', 'weight' => 2.75],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Electrical Plan', 'name' => 'Electrical Standard Attachment', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Electrical Plan', 'name' => 'Schedule of Electrical Equipments', 'weight' => 2.00],
            ['section' => 'In Progress Design Plan - UTP/COPPER', 'subsection' => 'Electrical Plan', 'name' => 'List of Electrical Equipments', 'weight' => 2.00],
        ];

        $familia = [
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Cover Page', 'name' => 'General Notes, Legend & Vicinity Map', 'weight' => 10.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Backbone and Backhaul', 'name' => 'Site Development Plan', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Backbone and Backhaul', 'name' => 'Backbone/Backhaul Cabling with photo location', 'weight' => 3.50],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Backbone and Backhaul', 'name' => 'MDF Cabling with photo location', 'weight' => 3.50],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Backbone and Backhaul', 'name' => 'Standard Attachment 1', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Backbone and Backhaul', 'name' => 'Standard Attachment 2', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Backbone and Backhaul', 'name' => 'Vertical Distribution Riser Diagram', 'weight' => 5.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Backbone and Backhaul', 'name' => 'AP Frequency Channel Standard', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Backbone and Backhaul', 'name' => 'Schedule of Fiber Loss', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Backbone and Backhaul', 'name' => 'Backhaul Cabling for LCP with photo', 'weight' => 1.50],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Backbone and Backhaul', 'name' => 'Distribution Cabling for LCP with photo', 'weight' => 1.50],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Access Point', 'name' => 'Heat Map - RF Simulation', 'weight' => 4.50],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Access Point', 'name' => 'Distribution Cabling: Horizontal', 'weight' => 3.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Access Point', 'name' => 'Distribution Cabling: Pvc pipe', 'weight' => 3.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Access Point', 'name' => 'Distribution Cabling: Manhole location', 'weight' => 3.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Access Point', 'name' => 'Manhole Location (photo)', 'weight' => 3.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Access Point', 'name' => 'Manhole Standard Details', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Access Point', 'name' => 'List of Data Equipments', 'weight' => 2.50],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Electrical Plan', 'name' => 'General Notes, Legend & Schedule of Load Calculation', 'weight' => 3.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Electrical Plan', 'name' => 'UPS Inverter Diagram', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Electrical Plan', 'name' => 'UPS Inverter Cabling with photo location', 'weight' => 4.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Electrical Plan', 'name' => 'Electrical Vertical Riser Diagram', 'weight' => 5.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Electrical Plan', 'name' => 'Electrical Horizontal Layout', 'weight' => 4.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Electrical Plan', 'name' => 'Electrical Standard Attachment', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - WiFi Direct Familia', 'subsection' => 'Electrical Plan', 'name' => 'List of Electrical Equipments', 'weight' => 2.00],
        ];

        $shareConnect = [
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Cover Page', 'name' => 'General Notes, Legend & Vicinity Map', 'weight' => 10.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Backbone and Backhaul', 'name' => 'Backbone Cabling for ISP: Site Development Plan', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Backbone and Backhaul', 'name' => 'Backhaul Cabling with photo location', 'weight' => 4.50],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Backbone and Backhaul', 'name' => 'MDF Cabling with photo location', 'weight' => 4.50],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Backbone and Backhaul', 'name' => 'Standard Attachment 1', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Backbone and Backhaul', 'name' => 'Standard Attachment 2', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Backbone and Backhaul', 'name' => 'Standard Attachment 3', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Backbone and Backhaul', 'name' => 'Vertical Distribution Riser Diagram', 'weight' => 5.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Backbone and Backhaul', 'name' => 'AP Frequency Channel Standard', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Backbone and Backhaul', 'name' => 'Schedule of Fiber Loss', 'weight' => 1.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Access Point', 'name' => 'Heat Map - RF Simulation', 'weight' => 10.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Access Point', 'name' => 'Distribution Cabling: Horizontal', 'weight' => 5.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Access Point', 'name' => 'Distribution Cabling: Pvc pipe', 'weight' => 5.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Electrical Plan', 'name' => 'General Notes, Legend & Schedule of Load Calculation', 'weight' => 3.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Electrical Plan', 'name' => 'Electrical Details: Vertical riser Diagram, UPS Inverter Diagram & Schedule of Load Calculation per floor', 'weight' => 8.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Electrical Plan', 'name' => 'Electrical Horizontal Layout', 'weight' => 8.00],
            ['section' => 'In Progress Design Plan - Share Connect', 'subsection' => 'Electrical Plan', 'name' => 'Electrical Standard Attachment', 'weight' => 1.00],
        ];

        // sort_order bands (0s, 100s, 200s) keep a type's common + type-specific rows interleaved
        // correctly regardless of which type is queried.
        $this->insertRows('site_planning', null, $initialDraft, 0);
        $this->insertRows('site_planning', 'FIBER', $fiber, 100);
        $this->insertRows('site_planning', 'Residential', $utpCopper, 100);
        $this->insertRows('site_planning', 'FAMILIA', $familia, 100);
        $this->insertRows('site_planning', 'Share-Connect', $shareConnect, 100);
        $this->insertRows('site_planning', null, $finalChecking, 200);
    }

    private function seedMonitoringTemplates(): void
    {
        $residential = [
            ['section' => 'MDF Installation', 'name' => 'MDF Cabinet Installation (Tray, PDU, Bracket)', 'weight' => 2.00],
            ['section' => 'MDF Installation', 'name' => 'UPS', 'weight' => 2.00],
            ['section' => 'MDF Installation', 'name' => 'PVC Installation 2" x 3.00m (Includes installation of fittings and necessary attachment needed) ISP Provision', 'weight' => 1.00],
            ['section' => 'MDF Installation', 'name' => 'EMT Conduit Installation 1/2" x 3.00m (Includes Installation of fittings and necessary Attachment needed)', 'weight' => 1.00],
            ['section' => 'MDF Installation', 'name' => 'Electrical Wiring Layout (3.5mm2 THHN Wire from Tapping point to UPS and UPS to MDF Breaker to PDU)', 'weight' => 2.00],
            ['section' => 'MDF Installation', 'name' => 'ODF, OLT, switch and Exhaust Fan Installation', 'weight' => 2.00],
            ['section' => 'Vertical Distribution Provision Layout', 'subsection' => 'DATA', 'name' => 'Installation of 3U Rack', 'weight' => 2.00],
            ['section' => 'Vertical Distribution Provision Layout', 'subsection' => 'DATA', 'name' => 'Installation of Conduit and cable duct', 'weight' => 3.00],
            ['section' => 'Vertical Distribution Provision Layout', 'subsection' => 'DATA', 'name' => 'Installation Layout of Distribution UTP (IDF-IDF and IDF-SMDF)', 'weight' => 8.00],
            ['section' => 'Vertical Distribution Provision Layout', 'subsection' => 'DATA', 'name' => 'Termination of 4P UTP in Both ends', 'weight' => 1.00],
            ['section' => 'Vertical Distribution Provision Layout', 'subsection' => 'DATA', 'name' => 'Grooming, Tagging and Line Testing of UTP Cables', 'weight' => 1.00],
            ['section' => 'Vertical Distribution Provision Layout', 'subsection' => 'Electrical', 'name' => 'Installation of Breaker and Breaker Box', 'weight' => 2.00],
            ['section' => 'Vertical Distribution Provision Layout', 'subsection' => 'Electrical', 'name' => 'Installation Layout of 3x 3.5mm THHN wires', 'weight' => 8.00],
            ['section' => 'Vertical Distribution Provision Layout', 'subsection' => 'Electrical', 'name' => 'Installation of Electrical Conduits and Attachements', 'weight' => 3.00],
            ['section' => 'Vertical Distribution Provision Layout', 'subsection' => 'Electrical', 'name' => 'Tapping/Termination of Electrical Wires and Grooming inside Breaker Box', 'weight' => 1.00],
            ['section' => 'Vertical Distribution Provision Layout', 'subsection' => 'Electrical', 'name' => 'Termination of Floor switch going to Floor Breaker', 'weight' => 1.00],
            ['section' => 'Backhaul Installation', 'name' => 'Fiber Installation Layout (MDF - SMDF and SMDF - SMDF)', 'weight' => 10.00],
            ['section' => 'Backhaul Installation', 'name' => 'Fiber Splicing and Grooming', 'weight' => 5.00],
            ['section' => 'Backhaul Installation', 'name' => 'Fiber Patch Cable ODF Termination', 'weight' => 5.00],
            ['section' => 'SMDF Installation', 'name' => 'Installation of SMDF Cabinet (9U)', 'weight' => 1.00],
            ['section' => 'SMDF Installation', 'name' => 'Installation of SMDF Switch', 'weight' => 0.50],
            ['section' => 'SMDF Installation', 'name' => 'Installation of PDU', 'weight' => 0.50],
            ['section' => 'SMDF Installation', 'name' => 'Installation of SMDF Breaker and Breaker Box', 'weight' => 2.00],
            ['section' => 'SMDF Installation', 'name' => 'Installation of Electrical Conduits and Attachements', 'weight' => 3.00],
            ['section' => 'SMDF Installation', 'name' => 'Wiring Termination and Grooming', 'weight' => 1.50],
            ['section' => 'SMDF Installation', 'name' => 'Termination of UTP @ SMDF Side', 'weight' => 1.00],
            ['section' => 'SMDF Installation', 'name' => 'UTP Tagging and Grooming', 'weight' => 0.50],
            ['section' => 'Testing Infra', 'name' => 'UTP Termination Testing (Includes Line testing and Switch Connectivity Checking)', 'weight' => 5.00],
            ['section' => 'Testing Infra', 'name' => 'Fiber Testing (MDF-SMDF and SMDF-SMDF)', 'weight' => 5.00],
            ['section' => 'Testing Infra', 'name' => 'Data Consolidation and Initial Test Result Submission', 'weight' => 5.00],
            ['section' => 'Testing with Network', 'name' => 'Validation/Rechecking of Test result (Includes troubleshooting of Fiber if needed)', 'weight' => 5.00],
            ['section' => 'Testing with Network', 'name' => 'Line checking of switch and speedtesting', 'weight' => 10.00],
        ];

        $shareConnect = [
            ['section' => 'MDF Installation', 'name' => 'MDF Cabinet Installation (Tray, PDU, Bracket)', 'weight' => 2.00],
            ['section' => 'MDF Installation', 'name' => 'UPS', 'weight' => 2.00],
            ['section' => 'MDF Installation', 'name' => 'PVC Installation 2" x 3.00m (Includes installation of fittings and necessary attachment needed) ISP Provision', 'weight' => 1.00],
            ['section' => 'MDF Installation', 'name' => 'EMT Conduit Installation 1/2" x 3.00m (Includes Installation of fittings and necessary Attachment needed)', 'weight' => 1.00],
            ['section' => 'MDF Installation', 'name' => 'Electrical Wiring Layout (3.5mm2 THHN Wire from Tapping point to UPS and UPS to MDF Breaker to PDU)', 'weight' => 2.00],
            ['section' => 'MDF Installation', 'name' => 'ODF, OLT and Exhaust Fan Installation', 'weight' => 2.00],
            ['section' => 'Vertical Provision Layout', 'name' => 'Breaker and Breaker Box Installation', 'weight' => 1.50],
            ['section' => 'Vertical Provision Layout', 'name' => 'PVC Installation 1" x 3.00m (Includes installation of fittings and necessary attachment needed)', 'weight' => 1.00],
            ['section' => 'Vertical Provision Layout', 'name' => 'Electrical Wiring Layout (3.5mm2 THHN Wire from Tapping point/MDF Breaker to each floor Breaker)', 'weight' => 3.00],
            ['section' => 'Vertical Provision Layout', 'name' => 'Fiber Installation Layout (from MDF Cabinet to Floor Breaker)', 'weight' => 2.50],
            ['section' => 'Vertical Provision Layout', 'name' => 'Termination of Electrical Wiring (Breaker per Breaker)', 'weight' => 1.00],
            ['section' => 'Vertical Provision Layout', 'name' => 'Grooming of Electrical Wiring', 'weight' => 1.00],
            ['section' => 'Backhaul Installation', 'name' => 'Fiber Installation Layout (MDF to SMDF)', 'weight' => 1.50],
            ['section' => 'Backhaul Installation', 'name' => 'Fiber Splicing and Grooming', 'weight' => 2.50],
            ['section' => 'Backhaul Installation', 'name' => 'Fiber Patch Cable ODF Termination', 'weight' => 1.00],
            ['section' => 'Horizontal Provision', 'name' => 'IP65 Installation', 'weight' => 4.00],
            ['section' => 'Horizontal Provision', 'name' => 'PVC Installation 1/2" x 3.00m (Includes installation of fittings and necessary attachment needed)', 'weight' => 4.00],
            ['section' => 'Horizontal Provision', 'name' => 'Fiber Patch Cable and Electrical Flat Cord Installation Layout (Pulling)', 'weight' => 6.00],
            ['section' => 'Horizontal Provision', 'name' => 'Fiber Installation Layout (from Floor Breaker to First Split)', 'weight' => 6.00],
            ['section' => 'Horizontal Provision', 'name' => 'Unbalance Split box attachement to IP65 Cover', 'weight' => 3.00],
            ['section' => 'Horizontal Provision', 'name' => 'C.O. Installation and Termination', 'weight' => 5.00],
            ['section' => 'Horizontal Provision', 'name' => 'Megger Testing per Floor', 'weight' => 2.00],
            ['section' => 'SMDF Installation', 'name' => 'SMDF Cabinet Installation (Tray, PDU, Bracket)', 'weight' => 2.00],
            ['section' => 'SMDF Installation', 'name' => 'ODF Installation', 'weight' => 3.00],
            ['section' => 'Termination', 'name' => 'ODF Splicing (MDF - SMDF Backhaul) Include Termination @ ODF', 'weight' => 3.00],
            ['section' => 'Termination', 'name' => 'ODF Splicing (Distribution) Include Termination @ ODF', 'weight' => 6.00],
            ['section' => 'Termination', 'name' => 'Fiber Splicing (First Split) Include Grooming and termination of Unbalanced split', 'weight' => 6.00],
            ['section' => 'AP Installation', 'name' => 'AP Installation', 'weight' => 3.33],
            ['section' => 'AP Installation', 'name' => 'Fiber Patch Cable termination', 'weight' => 3.33],
            ['section' => 'AP Installation', 'name' => 'Power Cord Termination', 'weight' => 3.34],
            ['section' => 'Testing Infra', 'name' => 'LCP Fiber Testing (Backhaul per SMDF/LCP) Passed (Include resplice/Troubleshooting)', 'weight' => 3.33],
            ['section' => 'Testing Infra', 'name' => 'Distribution Fiber Testing (First Split to LCP) Passed (Include resplice/Troubleshooting)', 'weight' => 3.33],
            ['section' => 'Testing Infra', 'name' => 'Data Consolidation and Initial Test Result Submission', 'weight' => 3.34],
            ['section' => 'Testing with Network', 'name' => 'Validation/Rechecking of Test result (Includes troubleshooting of Fiber if needed)', 'weight' => 1.67],
            ['section' => 'Testing with Network', 'name' => 'Check Router', 'weight' => 1.66],
            ['section' => 'Testing with Network', 'name' => 'Speed Testing per Router', 'weight' => 1.67],
        ];

        $familia = [
            ['section' => 'MDF Installation', 'name' => 'MDF Cabinet Installation (Tray, PDU, Bracket)', 'weight' => 2.00],
            ['section' => 'MDF Installation', 'name' => 'UPS', 'weight' => 2.00],
            ['section' => 'MDF Installation', 'name' => 'PVC Installation 2" x 3.00m (Includes installation of fittings and necessary attachment needed) ISP Provision', 'weight' => 1.00],
            ['section' => 'MDF Installation', 'name' => 'EMT Conduit Installation 1/2" x 3.00m (Includes Installation of fittings and necessary Attachment needed)', 'weight' => 1.00],
            ['section' => 'MDF Installation', 'name' => 'Electrical Wiring Layout (3.5mm2 THHN Wire from Tapping point to UPS and UPS to MDF Breaker to PDU)', 'weight' => 2.00],
            ['section' => 'MDF Installation', 'name' => 'ODF, OLT and Exhaust Fan Installation', 'weight' => 2.00],
            ['section' => 'Vertical Distribution Provision Layout', 'name' => 'Installation of Distribution Boxes', 'weight' => 2.50],
            ['section' => 'Vertical Distribution Provision Layout', 'name' => 'Installation of Conduit and cable duct', 'weight' => 2.50],
            ['section' => 'Vertical Distribution Provision Layout', 'name' => 'Grooming of fibers @ Distribution box Side', 'weight' => 2.50],
            ['section' => 'Vertical Distribution Provision Layout', 'name' => 'Installation Layout of Distribution Fibers (LCP - NAP)', 'weight' => 2.50],
            ['section' => 'Backhaul Installation', 'name' => 'Fiber Installation Layout (MDF to LCP)', 'weight' => 10.00],
            ['section' => 'Backhaul Installation', 'name' => 'Fiber Splicing and Grooming', 'weight' => 5.00],
            ['section' => 'Backhaul Installation', 'name' => 'Fiber Patch Cable ODF Termination', 'weight' => 5.00],
            ['section' => 'LCP and NAP Installation', 'name' => 'Installation of Distribution Boxes', 'weight' => 2.00],
            ['section' => 'LCP and NAP Installation', 'name' => 'Installation of Conduit and cable duct', 'weight' => 2.00],
            ['section' => 'LCP and NAP Installation', 'name' => 'Grooming of fibers @ Distribution box Side', 'weight' => 1.00],
            ['section' => 'Termination (MDF, LCP and NAP)', 'name' => 'ODF Splicing (MDF - SMDF Backhaul) Include Termination @ ODF', 'weight' => 10.00],
            ['section' => 'Termination (MDF, LCP and NAP)', 'name' => 'ODF Splicing (Distribution) Include Termination @ ODF', 'weight' => 10.00],
            ['section' => 'Termination (MDF, LCP and NAP)', 'name' => 'Fiber Splicing (First Split) Include Grooming and termination of Unbalanced split', 'weight' => 10.00],
            ['section' => 'Testing Infra', 'name' => 'LCP Fiber Testing (Backhaul per SMDF/LCP) Passed (Include resplice/Troubleshooting)', 'weight' => 5.00],
            ['section' => 'Testing Infra', 'name' => 'Distribution Fiber Testing (First Split to LCP) Passed (Include resplice/Troubleshooting)', 'weight' => 5.00],
            ['section' => 'Testing Infra', 'name' => 'Data Consolidation and Initial Test Result Submission', 'weight' => 5.00],
            ['section' => 'Testing with Network', 'name' => 'Validation/Rechecking of Test result (Includes troubleshooting of Fiber if needed)', 'weight' => 5.00],
            ['section' => 'Testing with Network', 'name' => 'Speed Testing per Router', 'weight' => 5.00],
        ];

        $this->insertRows('monitoring', 'Residential', $residential, 0);
        $this->insertRows('monitoring', 'Share-Connect', $shareConnect, 0);
        $this->insertRows('monitoring', 'FAMILIA', $familia, 0);
    }

    /** @param  array<int, array{section?: string, subsection?: string, name: string, weight: float}>  $tasks */
    private function insertRows(string $context, ?string $designPlanType, array $tasks, int $sortOrderStart): void
    {
        $now = now();

        DB::table('task_templates')->insert(array_values(array_map(
            fn (array $task, int $index): array => [
                'context' => $context,
                'design_plan_type' => $designPlanType,
                'section' => $task['section'] ?? null,
                'subsection' => $task['subsection'] ?? null,
                'name' => $task['name'],
                'weight' => $task['weight'],
                'sort_order' => $sortOrderStart + $index,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $tasks,
            array_keys($tasks)
        )));
    }
};
