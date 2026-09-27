<?php
/**
 * Plugin Name: SoftZor Taxonomy Migration Tool
 * Description: Safely migrates and standardizes the 57 B2B categories, populates _key_functions, and re-links software cards.
 * Version: 1.0.0
 * Author: Lyra / SoftZor
 */

if (!defined('ABSPATH')) {
    exit;
}

function softzor_get_migration_categories() {
    return [
    [
        'name' => 'Accounting & Financial Management',
        'slug' => 'accounting-finance',
        'parent_slug' => 'business-operations',
        'description' => '',
        'key_functions' => 'General Ledger Accounting, Invoicing and Billing, Accounts Payable & Receivable, Cash Flow Forecasting, Bank Feeds and Reconciliation, Tax Compliance and Filing, Multi-Currency Support, Financial Statements and Reporting, Expense Tracking and Auditing'
    ],
    [
        'name' => 'AgTech & Farm Management Software',
        'slug' => 'agtech-farm-management',
        'parent_slug' => 'other-industries',
        'description' => '',
        'key_functions' => 'Crop Planning and Yield Monitoring, Field Mapping and GPS Guidance, Livestock Herd Tracking, Weather Data Integration, Soil Health and Sensor Telemetry, Equipment Maintenance and Telematics, Farm Accounting and Cost Tracking, Labor and Task Dispatch'
    ],
    [
        'name' => 'AI Chatbots & Conversational AI',
        'slug' => 'chatbots-conversational-ai',
        'parent_slug' => 'sales-marketing-crm',
        'description' => '',
        'key_functions' => 'Natural Language Processing (NLP), Multichannel Deployment (Web, WhatsApp, Slack), LLM Prompt Orchestration, Automated Lead Qualification, Human Handoff and Ticket Routing, Knowledge Base RAG Ingestion, Sentiment and Intent Analysis, Conversational Analytics'
    ],
    [
        'name' => 'Amazon & Marketplace Tools',
        'slug' => 'amazon-tools',
        'parent_slug' => 'ecommerce-software',
        'description' => '',
        'key_functions' => 'Inventory and FBA Management, Advertising and PPC Automation, Pricing and Repricing Strategy, Financial Analytics and Profitability, Order and Fulfillment Processing, Customer Feedback and Review Management, Product Content and Listing Optimization, Market Intelligence and Competitor Tracking, Supply Chain and Procurement Planning, Multichannel Integration and Syncing'
    ],
    [
        'name' => 'Applicant Tracking Systems (ATS)',
        'slug' => 'applicant-tracking-systems',
        'parent_slug' => 'hr-recruiting',
        'description' => '',
        'key_functions' => 'Job Board Posting and Distribution, Resume Parsing and Candidate Search, Interview Scheduling and Video Screening, Automated Candidate Scoring, Offer Letter Management, Background Check Integration, Talent Pool and Sourcing CRM, EEO/DEI Compliance Reporting'
    ],
    [
        'name' => 'Auto Repair Shop Software',
        'slug' => 'auto-repair-software',
        'parent_slug' => 'field-service-auto',
        'description' => '',
        'key_functions' => 'Work Order and Repair Estimating, Parts Inventory and Ordering, Digital Vehicle Inspections (DVI), Customer Text Messaging and Approvals, Technician Time Tracking, Fleet Billing and Maintenance, QuickBooks Accounting Integration, VIN Decoding and History Lookup'
    ],
    [
        'name' => 'Beauty, Salon & Fitness Software',
        'slug' => 'beauty-fitness-software',
        'parent_slug' => 'field-service-auto',
        'description' => '',
        'key_functions' => 'Online Booking and Class Scheduling, Membership and Recurring Billing, Client Profile and Treatment History, POS and Retail Checkout, Automated SMS/Email Reminders, Staff Commission and Payroll Tracking, Mobile Client App, Resource and Room Allocation'
    ],
    [
        'name' => 'Business Intelligence & Data Analytics (BI)',
        'slug' => 'bi-data-analytics',
        'parent_slug' => 'business-operations',
        'description' => '',
        'key_functions' => 'Interactive Dashboards and Data Visualizations, ETL Data Pipeline Integration, SQL and NoSQL Query Builder, Predictive Modeling and Machine Learning, Automated Scheduled Reports, Embedded Analytics and White-Labeling, Self-Service Data Exploration, Granular Role-Based Access Control'
    ],
    [
        'name' => 'Cloud Storage & File Sharing',
        'slug' => 'cloud-storage',
        'parent_slug' => 'it-development',
        'description' => '',
        'key_functions' => 'Encrypted File Storage and Sharing, Team Folder Permissions and Access Tiers, Version History and File Recovery, Automated Desktop and Mobile Sync, Large File Transfer and Link Expiration, In-Transit and At-Rest Encryption, Enterprise Identity SSO/SAML, Audit Logging and Compliance'
    ],
    [
        'name' => 'Construction Management Software',
        'slug' => 'construction-management',
        'parent_slug' => 'real-estate-construction',
        'description' => '',
        'key_functions' => 'Project Scheduling and Gantt Charts, Blueprint and Document Markup, Job Costing and Budget Tracking, Subcontractor Bid Management, Daily Field Reporting, Change Order Processing, RFI and Submittal Workflows, Safety and Punch List Tracking'
    ],
    [
        'name' => 'Content & AI Marketing',
        'slug' => 'content-ai-marketing',
        'parent_slug' => 'marketing-tools',
        'description' => '',
        'key_functions' => 'AI Copywriting and Content Generation, Brand Voice Customization, SEO Keyword Integration, Multi-Language Translation and Localization, Plagiarism and AI Detection Checking, Editorial Calendar and Scheduling, Image and Asset Generation, Bulk Content Production'
    ],
    [
        'name' => 'CRM Software',
        'slug' => 'crm-software',
        'parent_slug' => 'sales-marketing-crm',
        'description' => '',
        'key_functions' => 'Contact and Lead Management, Visual Sales Pipeline Tracking, Email and Phone Interaction Logging, Task and Appointment Automation, Deal Stage Forecasting, Mobile CRM Access, Third-Party App Integrations, Custom Reporting and Analytics'
    ],
    [
        'name' => 'Customer Support & Helpdesk CRM',
        'slug' => 'customer-support-crm',
        'parent_slug' => 'crm-software',
        'description' => '',
        'key_functions' => 'Omnichannel Ticket Management, Shared Team Inboxes, SLA Management and Escalation Rules, Customer Self-Service Knowledge Base, Live Chat and In-App Messaging, CSAT and Support Analytics, Automated Canned Responses, Macro and Automation Triggers'
    ],
    [
        'name' => 'Cybersecurity & Threat Protection',
        'slug' => 'cybersecurity',
        'parent_slug' => 'it-development',
        'description' => 'Best 🛡️ Cybersecurity software on SoftZor.',
        'key_functions' => 'Endpoint Detection and Response (EDR), Threat Intelligence and Vulnerability Scanning, Security Information and Event Management (SIEM), Multi-Factor Authentication (MFA), Firewall and Network Traffic Inspection, Phishing Simulation and Training, Compliance Audit Automation, Incident Response Workflows'
    ],
    [
        'name' => 'Document Management & E-Signature',
        'slug' => 'document-management-esignature',
        'parent_slug' => 'business-operations',
        'description' => '',
        'key_functions' => 'Legally Binding Electronic Signatures, Document Template Creation, Audit Trail and Certificate of Completion, PDF Editing and Redaction, Workflow Approval Routing, Cloud Storage Integrations, Mobile Signing and Drawing, Automated Signature Reminders'
    ],
    [
        'name' => 'E-Commerce & Retail',
        'slug' => 'ecommerce-software',
        'parent_slug' => 'sales-marketing-crm',
        'description' => '',
        'key_functions' => 'Product Catalog Management, Multi-Gateway Payment Processing, Shopping Cart and Checkout Optimization, Inventory and Order Tracking, Shipping and Label Generation, Customer Account Management, Discount and Coupon Rules, Tax Calculation Automation'
    ],
    [
        'name' => 'E-Commerce Platforms (CMS)',
        'slug' => 'ecommerce-platforms',
        'parent_slug' => 'ecommerce-software',
        'description' => '',
        'key_functions' => 'Hosted Storefront Builder, Customizable Themes and Mobile Optimization, Headless API Architecture, International Currency and Localization, Product Variant Management, SEO Metadata Control, App Marketplace Extensions, High-Volume Traffic Scalability'
    ],
    [
        'name' => 'Electronic Health Records (EHR / EMR)',
        'slug' => 'ehr-emr-systems',
        'parent_slug' => 'healthcare-software',
        'description' => '',
        'key_functions' => 'HIPAA-Compliant Patient Charting, Clinical Decision Support, E-Prescribing (eRx) and Lab Orders, Patient Portal and Messaging, Medical Billing and Coding (ICD-10), Telehealth and Virtual Care Visits, Appointment Scheduling and Intake, Insurance Eligibility Verification'
    ],
    [
        'name' => 'Email Marketing',
        'slug' => 'email-marketing',
        'parent_slug' => 'marketing-tools',
        'description' => 'Best Email Marketing software and tools reviewed by SoftZor.',
        'key_functions' => 'Drag-and-Drop Email Builder, Drip Campaigns and Autoresponders, Audience Segmentation and Tagging, A/B Split Testing, Deliverability Optimization, Real-Time Open and Click Tracking, Signup Forms and Popups, Dynamic Personalization Tags'
    ],
    [
        'name' => 'ERP Systems',
        'slug' => 'erp-systems',
        'parent_slug' => 'business-operations',
        'description' => 'Best 💼 ERP Systems software on SoftZor.',
        'key_functions' => 'Core Financial and Ledger Consolidation, Supply Chain and Inventory Tracking, Manufacturing and Assembly Control, Human Resource and Payroll Modules, Sales Order and Fulfillment Workflows, Multi-Company and Multi-Currency, Executive BI Reporting, Role-Based Security Governance'
    ],
    [
        'name' => 'Event Management & Ticketing',
        'slug' => 'event-management',
        'parent_slug' => 'other-industries',
        'description' => '',
        'key_functions' => 'Online Ticket Sales and Registration, Custom Registration Forms, Badge Printing and QR Code Check-In, Event Agenda and Speaker Management, Sponsor and Exhibitor Portals, Virtual and Hybrid Event Streaming, Email Reminders and Marketing, Attendee Engagement and Polling'
    ],
    [
        'name' => 'Field Services & Auto Care',
        'slug' => 'field-service-auto',
        'parent_slug' => 'industry-solutions',
        'description' => '',
        'key_functions' => 'Job Dispatch and Technician Routing, GPS Vehicle Tracking, Mobile Work Order Management, On-Site Invoicing and Payment Collection, Preventive Maintenance Schedules, Parts and Asset Tracking, Customer Notification Alerts, Digital Signature Capture'
    ],
    [
        'name' => 'Financial Planning & Analysis (FP&A)',
        'slug' => 'fpa-software',
        'parent_slug' => 'accounting-finance',
        'description' => '',
        'key_functions' => 'Budgeting and Rolling Forecasting, Scenario Planning and What-If Analysis, Financial Statement Consolidation, Headcount and Workforce Planning, Variance Analysis and KPI Dashboards, Direct ERP and GL Integration, Driver-Based Modeling, Collaborative Budget Workflows'
    ],
    [
        'name' => 'Healthcare & Medical Software',
        'slug' => 'healthcare-software',
        'parent_slug' => 'industry-solutions',
        'description' => '',
        'key_functions' => 'Patient Scheduling and Registration, Electronic Claims Processing, Practice Management and Billing, HIPAA Compliance and Security, Clinical Workflow Automation, Telemedicine Video Consultations, Patient Satisfaction Surveys, Provider Credentialing Tracking'
    ],
    [
        'name' => 'Hospitality, Restaurant & Travel',
        'slug' => 'hospitality-travel',
        'parent_slug' => 'industry-solutions',
        'description' => '',
        'key_functions' => 'Property Management System (PMS), Central Reservation System (CRS), Channel Manager and OTA Sync, Guest Folio and Billing, Housekeeping and Maintenance Dispatch, Dynamic Pricing Engine, Guest Mobile Check-In, F&B Point of Sale Integration'
    ],
    [
        'name' => 'Hotel Management Systems (PMS)',
        'slug' => 'hotel-pms-software',
        'parent_slug' => 'hospitality-travel',
        'description' => '',
        'key_functions' => 'Room Availability and Reservation Matrix, Front Desk Check-In/Check-Out, Direct Booking Engine, Housekeeping Status Tracking, Night Audit Automation, OTA Channel Integration (Booking, Expedia), Rate Management and Packages, Guest Communication and Folios'
    ],
    [
        'name' => 'HR & Talent Management',
        'slug' => 'hr-recruiting',
        'parent_slug' => 'business-operations',
        'description' => '',
        'key_functions' => 'Candidate Sourcing and Talent CRM, Employee Onboarding Workflows, Performance Reviews and 360 Feedback, Time Off and Attendance Tracking, Employee Directory and Org Chart, Compensation Management, Training and Skill Development, Offboarding and Exit Interviews'
    ],
    [
        'name' => 'HRIS & Payroll Software',
        'slug' => 'hris-payroll',
        'parent_slug' => 'hr-recruiting',
        'description' => '',
        'key_functions' => 'Automated Direct Deposit Payroll, Tax Filing and W-2/1099 Generation, Benefits Administration and Open Enrollment, Time and Attendance Clocking, Self-Service Employee Portal, HR Compliance and Document Storage, PTO Accrual Tracking, Overtime and Wage Rule Automation'
    ],
    [
        'name' => 'Inventory, Warehouse & Supply Chain (WMS / SCM)',
        'slug' => 'inventory-wms-scm',
        'parent_slug' => 'business-operations',
        'description' => '',
        'key_functions' => 'Barcode and RFID Scanning, Multi-Warehouse Stock Tracking, Order Picking and Packing Workflows, Reorder Point Alerts and Automated POs, Batch and Expiration Date Tracking, Carrier Shipping Integrations, Cycle Counting and Inventory Audits, Supply Chain Logistics Visibility'
    ],
    [
        'name' => 'Learning Management Systems (LMS)',
        'slug' => 'lms-learning-management',
        'parent_slug' => 'other-industries',
        'description' => '',
        'key_functions' => 'Course Authoring and Video Hosting, SCORM and xAPI Compliance, Quizzes, Exams and Certification, Student Progress Tracking, Collaborative Discussion Forums, Blended and Live Virtual Classrooms, Mobile Learning Access, White-Label Academy Branding'
    ],
    [
        'name' => 'Legal Practice Management Software',
        'slug' => 'legal-software',
        'parent_slug' => 'other-industries',
        'description' => 'Best ⚖️ Software for Lawyers and Law Firms software and tools reviewed by SoftZor.',
        'key_functions' => 'Matter and Case Management, Legal Time Tracking and Billable Hours, Trust Accounting (IOLTA Compliant), Conflict of Interest Checking, Document Assembly and Legal Templates, Court Calendar and Docketing Rules, Secure Client Portal, Legal E-Billing (LEDES Format)'
    ],
    [
        'name' => 'Marketing CRM & Automation',
        'slug' => 'marketing-crm',
        'parent_slug' => 'crm-software',
        'description' => '',
        'key_functions' => 'Lead Scoring and Nurturing Workflows, Lifecycle Stage Tracking, Inbound Form and Landing Page Builder, Multi-Touch Revenue Attribution, Email and SMS Broadcasts, Website Visitor Identification, Dynamic Website Personalization, Marketing ROI Reporting'
    ],
    [
        'name' => 'Marketing Tools',
        'slug' => 'marketing-tools',
        'parent_slug' => 'sales-marketing-crm',
        'description' => '',
        'key_functions' => 'Campaign Management, Marketing Automation, Content Management, Customer Data Platform, Lead Generation and Scoring, Social Media Management, Search Engine Optimization, Email Marketing, Analytics and Attribution, Advertising Management, Affiliate and Influencer Marketing, Event Marketing'
    ],
    [
        'name' => 'No-Code & Low-Code Development Platforms',
        'slug' => 'no-code-platforms',
        'parent_slug' => 'it-development',
        'description' => '',
        'key_functions' => 'Visual Drag-and-Drop UI Builder, Relational Database Modeling, REST API and Webhook Connectors, Workflow Automation and Logic Rules, Custom CSS and JavaScript Extensibility, Responsive Web and Mobile Layouts, Role-Based Access Control, Single-Click Cloud Hosting and Deployment'
    ],
    [
        'name' => 'Other Industry Verticals',
        'slug' => 'other-industries',
        'parent_slug' => 'industry-solutions',
        'description' => '',
        'key_functions' => 'Vertical-Specific Business Logic, Industry Standard Compliance, Custom Field Workflows, Specialized Billing and Invoicing, Partner and Supplier Portals, Regulatory Audit Logging, Industry API Connectors, Specialized Reporting Modules'
    ],
    [
        'name' => 'Payment Processing & Gateways',
        'slug' => 'payment-gateways',
        'parent_slug' => 'ecommerce-software',
        'description' => 'Best Payment Gateways software and solutions on SoftZor.',
        'key_functions' => 'Credit Card and Debit Processing, Digital Wallets (Apple Pay, Google Pay), Fraud Detection and 3D Secure 2, Recurring Subscription Billing, Multi-Currency Settlement, PCI-DSS Level 1 Compliance, Developer REST APIs and SDKs, Automated Chargeback Dispute Handling'
    ],
    [
        'name' => 'POS Systems & Retail Software',
        'slug' => 'pos-systems',
        'parent_slug' => 'ecommerce-software',
        'description' => 'Best 💳 POS Systems software on SoftZor.',
        'key_functions' => 'Touchscreen Cash Register and Barcode Checkout, Integrated Card Reader and Contactless Payments, Real-Time Inventory Sync Across Stores, Cash Drawer and Receipt Printing, Customer Loyalty and Gift Cards, Offline Transaction Processing, Shift and Register Reconciliation, Employee Sales Tracking'
    ],
    [
        'name' => 'Project & Task Management',
        'slug' => 'project-management',
        'parent_slug' => 'business-operations',
        'description' => '',
        'key_functions' => 'Task Planning and Scheduling, Resource Management, Time and Expense Tracking, Collaboration and Communication, Document and Asset Management, Portfolio and Program Management, Budgeting and Financial Tracking, Agile and Workflow Automation, Reporting and Analytics, Risk and Issue Management'
    ],
    [
        'name' => 'Real Estate & Construction',
        'slug' => 'real-estate-construction',
        'parent_slug' => 'industry-solutions',
        'description' => '',
        'key_functions' => 'Property Listing Syndication, Real Estate Lead CRM, Construction Estimating and Takeoffs, Subcontractor Scheduling, Project Cost and Budget Tracking, Contract and Lien Waiver Management, Drone and Site Photo Documentation, Client Construction Portals'
    ],
    [
        'name' => 'Real Estate CRM & Property Management',
        'slug' => 'real-estate-crm',
        'parent_slug' => 'real-estate-construction',
        'description' => '',
        'key_functions' => 'MLS Listing Integration and Sync, Buyer and Seller Lead Routing, Automated Property Alerts via Email/SMS, Open House Check-In Forms, Transaction and Escrow Coordination, Document E-Signature Integration, Agent Commission Tracking, Drip Campaigns for Homebuyers'
    ],
    [
        'name' => 'Remote Work & Team Collaboration',
        'slug' => 'remote-work-collaboration',
        'parent_slug' => 'collaboration-communications',
        'description' => '',
        'key_functions' => 'Real-Time Collaborative Canvas, Screen Sharing and Remote Presentation, Asynchronous Video Messaging, Digital Whiteboarding and Brainstorming, Shared Team Workspaces, Cloud File Co-Authoring, Presence and Status Indicators, Cross-Platform Desktop/Mobile Apps'
    ],
    [
        'name' => 'Restaurant POS & Management',
        'slug' => 'restaurant-management',
        'parent_slug' => 'hospitality-travel',
        'description' => '',
        'key_functions' => 'Table Layout and Floor Management, Kitchen Display System (KDS), Menu and Recipe Costing, Online Food Ordering Integration, Table-Side Tablet Ordering, Inventory Depletion and Ingredient Tracking, Tip Pooling and Split Checks, Delivery Driver Dispatch'
    ],
    [
        'name' => 'Sales CRM',
        'slug' => 'sales-crm',
        'parent_slug' => 'crm-software',
        'description' => '',
        'key_functions' => 'Sales Pipeline Stages and Funnel Tracking, Automated Follow-Up Sequences, Inbound and Outbound Calling with Voicemail Drop, Email Tracking and Templates, Deal Value and Probability Forecasting, Territory and Lead Assignment Rules, Meeting Scheduler Integration, Quota and Leaderboard Gamification'
    ],
    [
        'name' => 'SEO Tools & Search Intelligence',
        'slug' => 'seo-tools',
        'parent_slug' => 'marketing-tools',
        'description' => 'Best SEO Tools software and tools reviewed by SoftZor.',
        'key_functions' => 'Keyword Research and Management, On-Page SEO Auditing, Backlink Analysis and Monitoring, Rank Tracking, Technical SEO Crawling, Content Optimization, Competitor Intelligence, Local SEO Management, Search Console Integration, SEO Reporting and Analytics, Link Building Outreach, AI-Powered Content Generation'
    ],
    [
        'name' => 'Small Business Accounting',
        'slug' => 'accounting-software',
        'parent_slug' => 'accounting-finance',
        'description' => 'Best Accounting software and tools reviewed by SoftZor.',
        'key_functions' => 'General Ledger Management, Accounts Payable and Receivable, Fixed Asset Management, Tax Compliance and Reporting, Payroll and Benefits Administration, Cash Flow and Treasury Management, Financial Planning and Analysis, Inventory and Cost Accounting, Multi-Entity Consolidation, Audit Trail and Compliance, Expense Management, Bank Reconciliation and Integration'
    ],
    [
        'name' => 'Social Media Management (SMM)',
        'slug' => 'social-media-management',
        'parent_slug' => 'marketing-tools',
        'description' => '',
        'key_functions' => 'Multi-Account Social Scheduling, Unified Social Inbox and Comment Moderation, Social Listening and Brand Mentions, Visual Content Planner and Grid Preview, Analytics and Engagement Benchmarks, Team Approval Workflows, Hashtag Generator and Library, Best Time to Post Optimization'
    ],
    [
        'name' => 'Team Messengers & Chat',
        'slug' => 'team-messengers',
        'parent_slug' => 'remote-work-collaboration',
        'description' => '',
        'key_functions' => 'Direct and Channel-Based Messaging, Threaded Discussions, Voice and Video Huddle Calls, File Sharing and In-Line Preview, Custom Emoji and Message Reactions, Searchable Chat History Archive, Notification Snoozing and Do Not Disturb, Third-Party Bot and Webhook Integrations'
    ],
    [
        'name' => 'Travel Agency Software',
        'slug' => 'travel-agency-software',
        'parent_slug' => 'hospitality-travel',
        'description' => 'Best ✈️ Software for Travel Agencies software and tools reviewed by SoftZor.',
        'key_functions' => 'Itinerary Builder and Quoting, GDS and Flight Booking Sync, Hotel and Tour Supplier Integrations, Client Travel Profile Management, Commission Tracking and Splitting, Automated Travel Document Generation, Currency Conversion and Invoicing, Mobile Traveler Itinerary App'
    ],
    [
        'name' => 'Veterinary Practice Management',
        'slug' => 'veterinary-software',
        'parent_slug' => 'healthcare-software',
        'description' => '',
        'key_functions' => 'Pet Medical Record Charting (SOAP Notes), Rabies and Vaccine Certificate Tracking, Boarding and Grooming Scheduling, In-Clinic Diagnostic and Lab Equipment Sync, Prescription and Pharmacy Inventory, Client Reminders for Vaccinations, Payment and Wellness Plan Billing, Multi-Species Support'
    ],
    [
        'name' => 'Video Conferencing',
        'slug' => 'video-conferencing',
        'parent_slug' => 'remote-work-collaboration',
        'description' => 'Comprehensive catalog of Video Conferencing on SoftZor.',
        'key_functions' => 'HD Video and Crystal-Clear Audio Calls, Screen Sharing and Window Annotation, Meeting Recording and Cloud Storage, AI Automated Transcription and Meeting Summaries, Virtual Backgrounds and Noise Suppression, Breakout Rooms for Group Discussions, End-to-End Encryption, Calendar and Outlook/Google Integration'
    ],
    [
        'name' => 'VoIP & Cloud Business Phone Systems',
        'slug' => 'business-phone-voip',
        'parent_slug' => 'collaboration-communications',
        'description' => '',
        'key_functions' => 'Cloud PBX Virtual Phone System, Interactive Voice Response (IVR) Auto-Attendant, Call Recording and Transcription, Voicemail-to-Email Delivery, Softphone App for Mobile and Desktop, Call Queues and Ring Groups, Business SMS and MMS Texting, International Virtual Phone Numbers'
    ],
    [
        'name' => '🏢 Industry-Specific Solutions',
        'slug' => 'industry-solutions',
        'parent_slug' => '',
        'description' => 'Best 💼 Industry-Specific Solutions software on SoftZor.',
        'key_functions' => 'Asset Lifecycle Management, Supply Chain Orchestration, Production Planning and Scheduling, Quality Control and Compliance, Field Service Operations, Industrial IoT and Telemetry, Regulatory Reporting and Documentation, Resource Capacity Planning, Maintenance and Repair Operations, Inventory and Warehouse Logistics, Project and Engineering Management, Environmental Health and Safety Management'
    ],
    [
        'name' => '💻 IT, Security & Development',
        'slug' => 'it-development',
        'parent_slug' => '',
        'description' => '',
        'key_functions' => 'Software Development Lifecycle Management, Cloud Infrastructure and Orchestration, Cybersecurity and Threat Intelligence, Identity and Access Management, IT Service Management, Data Engineering and Analytics, Network Monitoring and Performance, API Management and Integration, DevOps and Continuous Delivery, Quality Assurance and Automated Testing, Database Administration and Storage, Compliance and Governance Management'
    ],
    [
        'name' => '💼 Business Operations & Management',
        'slug' => 'business-operations',
        'parent_slug' => '',
        'description' => '',
        'key_functions' => 'Enterprise Resource Planning, Customer Relationship Management, Human Capital Management, Supply Chain Management, Financial Accounting and Reporting, Business Process Automation, Project and Task Management, Business Intelligence and Analytics, Document and Content Management, Asset and Facility Management, Procurement and Vendor Management, Compliance and Risk Management'
    ],
    [
        'name' => '📈 Sales, Marketing & Customer Care',
        'slug' => 'sales-marketing-crm',
        'parent_slug' => '',
        'description' => '',
        'key_functions' => 'Customer Relationship Management, Marketing Automation, Sales Pipeline Management, Multichannel Communication, Lead Generation and Scoring, Customer Support and Ticketing, E-commerce and Order Management, Analytics and Business Intelligence, Content and Asset Management, Loyalty and Retention Programs'
    ],
    [
        'name' => '🤖 AI Tools & Autonomous Agents',
        'slug' => 'ai-tools-agents',
        'parent_slug' => 'it-development',
        'description' => '',
        'key_functions' => 'Autonomous Task Execution and Multi-Agent Orchestration, Foundation Model Switching (Claude, GPT, Gemini), API and Tool Calling Integrations, Vector Database and Long-Term Memory Retrieval, Code Generation and Self-Debugging, Document Parsing and Multi-Modal Reasoning, Custom Agent Persona and System Prompting, Cost and Token Usage Analytics'
    ],
    [
        'name' => '🤝 Collaboration & Communications',
        'slug' => 'collaboration-communications',
        'parent_slug' => '',
        'description' => '',
        'key_functions' => 'Team Messaging and Chat Channels, HD Video Conferencing and Screen Sharing, Cloud PBX and VoIP Phone Systems, Shared Document Co-Authoring, Digital Whiteboarding and Canvas, Asynchronous Video Messaging, Workflow and Task Notifications, Calendar and Meeting Scheduling Integration, Cross-Platform Mobile and Desktop Sync'
    ]
    ];
}

function softzor_run_category_migration() {
    if (!current_user_can('manage_options') && (!defined('WP_CLI') || !WP_CLI)) {
        return ['success' => false, 'error' => 'Permission denied'];
    }

    set_time_limit(300);

    $log = [];
    $log[] = 'Starting SoftZor Category Migration...';

    $categories = softzor_get_migration_categories();
    $slug_to_id = [];

    // --- PHASE 1: Create or Update Root Categories First ---
    $log[] = '--- Phase 1: Processing Root Pillars ---';
    foreach ($categories as $cat) {
        if (empty($cat['parent_slug'])) {
            $res = softzor_upsert_category($cat, 0);
            if ($res['success']) {
                $slug_to_id[$cat['slug']] = $res['term_id'];
                $log[] = "Root '{$cat['name']}' [{$cat['slug']}] -> ID: {$res['term_id']} ({$res['action']})";
            } else {
                $log[] = "ERROR inserting root '{$cat['name']}': " . $res['error'];
            }
        }
    }

    // --- Phase 2: Processing Subcategories ---
    $log[] = '--- Phase 2: Processing Subcategories ---';
    foreach ($categories as $cat) {
        if (!empty($cat['parent_slug'])) {
            $parent_slug = $cat['parent_slug'];
            $parent_id = isset($slug_to_id[$parent_slug]) ? $slug_to_id[$parent_slug] : 0;
            
            if (!$parent_id) {
                $parent_term = get_term_by('slug', $parent_slug, 'software_category');
                if ($parent_term && !is_wp_error($parent_term)) {
                    $parent_id = $parent_term->term_id;
                    $slug_to_id[$parent_slug] = $parent_id;
                }
            }

            $res = softzor_upsert_category($cat, $parent_id);
            if ($res['success']) {
                $slug_to_id[$cat['slug']] = $res['term_id'];
                $log[] = "Subcat '{$cat['name']}' [{$cat['slug']}] (parent: {$parent_slug} [ID: {$parent_id}]) -> ID: {$res['term_id']} ({$res['action']})";
            } else {
                $log[] = "ERROR inserting subcat '{$cat['name']}': " . $res['error'];
            }
        }
    }

    // --- Phase 3: Cleanup Obsolete CIS Categories (count == 0) ---
    $log[] = '--- Phase 3: Cleaning Obsolete Categories ---';
    $valid_slugs = array_flip(array_column($categories, 'slug'));
    $all_terms = get_terms([
        'taxonomy' => 'software_category',
        'hide_empty' => false,
    ]);

    $deleted_count = 0;
    foreach ($all_terms as $term) {
        if (!isset($valid_slugs[$term->slug])) {
            if ($term->count == 0) {
                wp_delete_term($term->term_id, 'software_category');
                $deleted_count++;
                $log[] = "Deleted obsolete term: '{$term->name}' [{$term->slug}] (ID: {$term->term_id})";
            } else {
                $log[] = "WARNING: Skipped term '{$term->name}' [{$term->slug}] because it has {$term->count} posts attached.";
            }
        }
    }
    $log[] = "Cleaned up {$deleted_count} obsolete categories.";

    // --- Phase 4: Trash Post 2035 if exists ---
    $log[] = '--- Phase 4: Trashing Test Post 2035 ---';
    $test_post = get_post(2035);
    if ($test_post) {
        wp_trash_post(2035);
        $log[] = "Post 2035 ('{$test_post->post_title}') moved to trash.";
    } else {
        $log[] = "Post 2035 not found or already deleted.";
    }

    // --- Phase 5: Re-link Existing Software Cards ---
    $log[] = '--- Phase 5: Re-linking Software Cards ---';
    $cards_map = [
        2039 => [ // Helium 10
            'slugs' => ['amazon-tools', 'ecommerce-software', 'sales-marketing-crm', 'bi-data-analytics'],
            'primary_slug' => 'amazon-tools',
        ],
        2040 => [ // Keepa
            'slugs' => ['amazon-tools', 'ecommerce-software', 'sales-marketing-crm', 'bi-data-analytics'],
            'primary_slug' => 'amazon-tools',
        ],
        2041 => [ // SmartScout
            'slugs' => ['amazon-tools', 'ecommerce-software', 'sales-marketing-crm', 'bi-data-analytics'],
            'primary_slug' => 'amazon-tools',
        ],
        2042 => [ // Sellerboard
            'slugs' => ['amazon-tools', 'ecommerce-software', 'sales-marketing-crm', 'bi-data-analytics'],
            'primary_slug' => 'amazon-tools',
        ],
        2043 => [ // Seller Assistant
            'slugs' => ['amazon-tools', 'ecommerce-software', 'sales-marketing-crm', 'bi-data-analytics'],
            'primary_slug' => 'amazon-tools',
        ],
        2038 => [ // ElevenLabs
            'slugs' => ['ai-tools-agents', 'content-ai-marketing', 'chatbots-conversational-ai', 'marketing-tools', 'sales-marketing-crm'],
            'primary_slug' => 'content-ai-marketing',
        ],
        2056 => [ // Base44
            'slugs' => ['no-code-platforms', 'it-development'],
            'primary_slug' => 'no-code-platforms',
        ],
    ];

    foreach ($cards_map as $post_id => $mapping) {
        $post = get_post($post_id);
        if (!$post) {
            $log[] = "Notice: Post ID {$post_id} not found on this site. Skipping.";
            continue;
        }

        $term_ids = [];
        foreach ($mapping['slugs'] as $s) {
            if (isset($slug_to_id[$s])) {
                $term_ids[] = (int) $slug_to_id[$s];
            } else {
                $t = get_term_by('slug', $s, 'software_category');
                if ($t && !is_wp_error($t)) {
                    $term_ids[] = (int) $t->term_id;
                }
            }
        }

        $primary_id = 0;
        if (isset($slug_to_id[$mapping['primary_slug']])) {
            $primary_id = (int) $slug_to_id[$mapping['primary_slug']];
        } else {
            $pt = get_term_by('slug', $mapping['primary_slug'], 'software_category');
            if ($pt && !is_wp_error($pt)) {
                $primary_id = (int) $pt->term_id;
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, $term_ids, 'software_category');
        }

        if ($primary_id) {
            if (function_exists('update_field')) {
                update_field('primary_category', $primary_id, $post_id);
            }
            update_post_meta($post_id, 'primary_category', $primary_id);
            update_post_meta($post_id, 'rank_math_primary_software_category', $primary_id);
        }

        $log[] = "Post {$post_id} ('{$post->post_title}') updated: assigned terms [" . implode(', ', $term_ids) . "], primary_category: {$primary_id}";
    }

    // --- Phase 6: Flush Caches ---
    $log[] = '--- Phase 6: Flushing Caches ---';
    clean_term_cache(array_values($slug_to_id), 'software_category');
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    if (has_action('litespeed_purge_all')) {
        do_action('litespeed_purge_all');
        $log[] = 'LiteSpeed Cache purged.';
    }

    $log[] = 'Migration Completed Successfully!';
    return [
        'success' => true,
        'slug_to_id' => $slug_to_id,
        'log' => $log,
    ];
}

function softzor_upsert_category($cat, $parent_id) {
    $existing = get_term_by('slug', $cat['slug'], 'software_category');
    
    if ($existing && !is_wp_error($existing)) {
        $term_id = $existing->term_id;
        $args = [
            'name' => $cat['name'],
            'parent' => (int) $parent_id,
        ];
        if (!empty($cat['description'])) {
            $args['description'] = $cat['description'];
        }
        wp_update_term($term_id, 'software_category', $args);
        $action = 'updated';
    } else {
        $args = [
            'slug' => $cat['slug'],
            'parent' => (int) $parent_id,
        ];
        if (!empty($cat['description'])) {
            $args['description'] = $cat['description'];
        }
        $inserted = wp_insert_term($cat['name'], 'software_category', $args);
        if (is_wp_error($inserted)) {
            return ['success' => false, 'error' => $inserted->get_error_message()];
        }
        $term_id = $inserted['term_id'];
        $action = 'created';
    }

    // Save key functions in term meta
    if (!empty($cat['key_functions'])) {
        update_term_meta($term_id, '_key_functions', $cat['key_functions']);
    }

    return [
        'success' => true,
        'term_id' => $term_id,
        'action' => $action
    ];
}

// Add Tools Menu Page
add_action('admin_menu', function () {
    add_management_page(
        'SoftZor Category Migration',
        'Category Migration',
        'manage_options',
        'softzor-category-migration',
        'softzor_render_category_migration_page'
    );
});

function softzor_render_category_migration_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $result = null;
    if (isset($_POST['run_softzor_migration']) && check_admin_referer('softzor_run_migration_nonce')) {
        $result = softzor_run_category_migration();
    }

    echo '<div class="wrap">';
    echo '<h1>🚀 SoftZor Taxonomy Migration Tool (Global B2B)</h1>';
    echo '<p>This tool synchronizes all 57 B2B categories across 5 pillars, populates standard macro-features (_key_functions), and re-links software cards.</p>';

    if ($result) {
        if ($result['success']) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Migration completed successfully!</strong></p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Error: ' . esc_html($result['error']) . '</strong></p></div>';
        }
        echo '<h3>Execution Log:</h3>';
        echo '<pre style="background:#fff; border:1px solid #ccd0d4; padding:15px; max-height:450px; overflow-y:scroll; font-size:13px; line-height:1.6;">';
        foreach ($result['log'] as $line) {
            echo esc_html($line) . "\n";
        }
        echo '</pre>';
    }

    echo '<form method="post" style="margin-top: 20px;">';
    wp_nonce_field('softzor_run_migration_nonce');
    echo '<p><button type="submit" name="run_softzor_migration" class="button button-primary button-hero">▶️ Execute Category Migration Now</button></p>';
    echo '</form>';
    echo '</div>';
}
