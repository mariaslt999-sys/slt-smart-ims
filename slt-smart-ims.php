<?php
/**
 * Plugin Name: SLT SMART IMS
 * Plugin URI: https://smartlifeteck.com/
 * Description: Open-Source 2-Way Google Sheets & WooCommerce Inventory Management System (IMS), Quick Laptop Purchase & Sale CRM, Team 1-Click Social Media / Haraj / WhatsApp Marketing Copy Hub, and Make.com AI Auto-Post Queue. Developed by Shahzad Babar | Smart Life Teck.
 * Version: 2.1.0
 * Author: Developer Shahzad | Smart Life Teck
 * Author URI: https://smartlifeteck.com/
 * License: GPLv2 or later
 * Text Domain: slt-smart-ims
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class SLT_Inventory_Marketing_Hub {

    const VERSION = '2.1.0';
    const OPT_SETTINGS = 'slt_ims_hub_settings';
    const OPT_INVENTORY = 'slt_ims_hub_inventory';
    const OPT_SALES = 'slt_ims_hub_sales';
    const OPT_QUEUE = 'slt_ims_hub_post_queue';
    const CRON_HOOK = 'slt_ims_process_scheduled_posts';

    // Default Connected Google Drive & Sheet IDs from Smart Life Teck Workspace
    const DEFAULT_MASTER_SHEET_ID = ''; // Configure your Master Google Sheet ID in Tab 6 Settings
    const DEFAULT_TEAM_SHEET_ID   = ''; // Configure your Team Live Stock Sheet ID in Tab 6 Settings
    const DEFAULT_QUEUE_SHEET_ID  = ''; // Configure your Auto-Post Queue Sheet ID in Tab 6 Settings
    const DEFAULT_DRIVE_FOLDER_ID = ''; // Configure your Google Drive Workspace Folder ID in Tab 6 Settings

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'on_activate'));
        register_deactivation_hook(__FILE__, array($this, 'on_deactivate'));

        add_action('init', array($this, 'maybe_seed_initial_data'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // AJAX endpoints for Admin & Frontend Team Portal
        add_action('wp_ajax_slt_ims_save_purchase', array($this, 'ajax_save_purchase'));
        add_action('wp_ajax_slt_ims_delete_sku', array($this, 'ajax_delete_sku'));
        add_action('wp_ajax_slt_ims_save_sale', array($this, 'ajax_save_sale'));
        add_action('wp_ajax_slt_ims_update_sale_status', array($this, 'ajax_update_sale_status'));
        add_action('wp_ajax_slt_ims_upload_csv', array($this, 'ajax_upload_csv'));
        add_action('wp_ajax_slt_ims_pull_google_sheet', array($this, 'ajax_pull_google_sheet'));
        add_action('wp_ajax_slt_ims_push_all_to_sheet', array($this, 'ajax_push_all_to_sheet'));
        add_action('wp_ajax_slt_ims_sync_woocommerce', array($this, 'ajax_sync_woocommerce'));
        add_action('wp_ajax_slt_ims_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_slt_ims_trigger_post', array($this, 'ajax_trigger_post'));

        // WooCommerce automatic 2-way order & stock hooks
        add_action('woocommerce_order_status_completed', array($this, 'on_woocommerce_order_completed'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'on_woocommerce_order_completed'), 10, 1);
        add_action('save_post_product', array($this, 'on_woocommerce_product_saved'), 20, 3);

        // Scheduled Make.com / Queue runner
        add_action(self::CRON_HOOK, array($this, 'process_scheduled_posts'));

        // Shortcodes:
        // 1) [slt_team_marketing_hub] -> Team-safe Marketing & 1-Click Copy Portal (hides purchase cost/profit)
        // 2) [slt_ims_portal] -> Full Admin/Team Portal on frontend
        add_shortcode('slt_team_marketing_hub', array($this, 'render_team_shortcode'));
        add_shortcode('slt_ims_portal', array($this, 'render_full_portal_shortcode'));
    }

    public function on_activate() {
        $this->maybe_seed_initial_data();
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        }
    }

    public function on_deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public function get_default_seed_data() {
        $json = <<<'SLTSEEDJSON'
{"purchases": [{"sku": "SLT-001", "brand": "Microsoft", "model_name": "Surface Laptop 3", "touch_type": "Touch", "processor": "i7", "generation": "10", "ram": "16", "storage": "256GB", "display_size": "13.5\"", "graphics": "Intel Iris Plus", "software": "Windows 11 Pro, MS Office, PDF Editor", "short_spec": "Microsoft Surface Laptop 3 , i7 10th 16/256GB 13.5\" Touch  Intel Iris Plus", "purchased_qty": 6, "live_qty": 6, "stock_status_label": "In Stock (6)", "unit_cost": 0.0, "shipping_cost": 0.0, "landed_cost": 0.0, "total_bill": 0.0, "retail_price": 1575.0, "discount": 75.0, "final_price": 1500.0, "regular_price": 3250.0, "supplier_name": "Authorized Supplier", "purchase_date": "2026-10-14", "long_spec": "Microsoft Surface Laptop 3 (13.5\")\n\nQuick Specifications\n\n✓ Intel Core i7 10th Gen (i7-1065G7)\n\n✓ 16 GB LPDDR4x\n\n✓ 256 GB NVMe SSD\n\n✓ 13.5\" PixelSense 10-Point Multi-Touch Display (2256 x 1504)\n\n✓ Intel Iris Plus Graphics\n\n✓ USB-C, USB-A, Surface Connect, 3.5mm Headphone Jack\n\n✓ Ultra-slim aluminum design, 10-point touchscreen & Windows Hello facial recognition\n\n✓ Condition: Excellent A+\n\n✓ Windows 11 Pro with Microsoft Office Life Time activated\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nLaptop Model:\nMicrosoft Surface Laptop 3 13.5\"\n\nSpecifications:\n\n* Processor:\nIntel Core i7-1065G7 (10th Gen, 1.30 GHz base frequency, up to 3.90 GHz boost speed, 4 cores, 8 threads, 8 MB Intel Smart Cache)\n\n\n* RAM:\n16 GB LPDDR4x 3733 MT/s (On-board / Non-upgradable)\n\n\n* Storage:\n256 GB PCIe NVMe M.2 SSD\n\n\n* Display:\n13.5\" PixelSense Display, 2256 x 1504 resolution (201 PPI), 3:2 aspect ratio, 10-point multi-touch, Surface Pen enabled, Gorilla Glass\n* Graphics:\nIntel Iris Plus Graphics (Integrated)\n* Wireless:\nWi-Fi 6 (802.11ax compatible) + Bluetooth 5.0 Wireless technology\n* Webcam:\n720p HD f/2.0 camera with Windows Hello IR face authentication\n* Audio:\nOmnisonic Speakers with Dolby Audio, Dual far-field Studio Mics\n* Ports:\n1 x USB-C, 1 x USB-A, 1 x Surface Connect port, 1 x 3.5 mm headphone jack\n* Battery:\nExcellent\n* Condition:\nExcellent A+\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nKey Features:\n\n✓ 10th Gen Intel Core i7 Processor — 4-core, 8-thread power for heavy multitasking & business productivity\n✓ 13.5\" PixelSense Touchscreen — Vibrant high-resolution 2256 x 1504 display with 10-point multi-touch response\n✓ 3:2 Aspect Ratio Screen — Extra vertical screen space ideal for viewing documents, spreadsheets, and web pages\n✓ Premium Aluminum Build — Ultra-slim, sleek, and durable premium design crafted for portability\n✓ Instant-On & Fast Charging — Quickly wake up from sleep and recharge up to 80% in about an hour\n✓ Windows Hello Facial Recognition — Instant, highly secure password-free login via IR front camera\n✓ Omnisonic Audio — Hidden speakers tuned with Dolby Audio for crisp, immersive sound delivery\n✓ Dual Far-Field Studio Mics — Smart background noise reduction for crystal-clear video calls and meetings\n✓ Wi-Fi 6 Connectivity — Next-generation ultra-fast wireless speed and stable network performance\n✓ USB-C & USB-A Versatility — Easy connectivity for external displays, docking stations, and older legacy peripherals\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSoftware Package (Included Free):\n\n✓ Windows 11 Pro — Genuine | Lifetime Activated\n✓ Microsoft Office — Genuine | Lifetime Activated\n✓ Foxit PDF Editor — Lifetime Activated\n✓ All Essential Drivers Installed\n✓ Windows Optimized for Best Performance\n✓ Ready for Work, Study, Business & Online Meetings\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nQuality Assurance & Warranty:\n\n✓ Every laptop is fully inspected — hardware, software, and all components tested before delivery\n\n✓ Latest Windows updates, drivers, and optimizations installed before shipping\n\n✓ No known issues or faults — clean, reliable, and ready to use from day one\n\n✓ 10 Days Checking Warranty — If any hardware issue is found within 10 days, laptop will be replaced with another available model\n\n✓ 6 Months Service Warranty — Free servicing and technical support for 6 months after purchase\n\n✓ Free Remote Software Support via AnyDesk — anytime you need it\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nDelivery & Payment:\n\n✓ Free Delivery Across Saudi Arabia\n✓ Cash On Delivery Available\n✓ Bank Transfer Accepted\n✓ Visit our branch to inspect before purchase\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSmart Life Teck Contacts:\n\n🌐 Website: https://smartlifeteck.com/\n📱 WhatsApp Catalog: https://wa.me/c/966553760854\n📞 WhatsApp: +966553760854\n📧 Email: smartlifeteck@gmail.com\n📱 @smartlifeteck on all social media platforms\n\n━━━━━━━━━━━━━━━━━━━━━━", "arabic_spec": "━━━━━━━━━━━━━━━━━━━━━\nلابتوب مايكروسوفت سيرفس لابتوب Microsoft Surface Laptop 3 (Model 1867)\n\nمواصفات سريعة:\n- المعالج: Intel Core i7-1065G7 (الجيل العاشر)\n- الذاكرة العشوائية (RAM): 16 جيجابايت LPDDR4x عالي السرعة\n- التخزين: 256 جيجابايت NVMe SSD\n- الشاشة: 13.5 بوصة لمس PixelSense بدقة 2K (2256x1504)\n- كرت الشاشة: Intel Iris Plus Graphics\n- الوزن: خفيف جداً (1.26 كجم فقط)\n- الحالة: ممتاز جداً A+ (مستعمل بحالة الجديد)\n\nالمواصفات التفصيلية:\n- المعالج: Intel Core i7-1065G7 رباعي النواة (8 مسارات) بسرعة تصل إلى 3.90GHz وذاكرة كاش 8MB\n- الرام: 16GB LPDDR4x بتردد 3733MHz لأداء سريع في تعدد المهام\n- التخزين: 256GB M.2 NVMe SSD فائق السرعة وقابل للترقية\n- الشاشة: 13.5 بوصة PixelSense تدعم اللمس المتعدد (10 نقاط) وقلم Surface وأبعاد 3:2 المريحة للعمل\n- الكاميرا: كاميرا أمامية عالية الوضوح 720p HD f2.0 مع كاميرا الأشعة تحت الحمراء IR\n- الصوت: سماعات Omnisonic مدمجة بتقنية Dolby Audio مع مايكروفونات ستوديو مزدوجة بعيدة المدى\n- المنافذ: منفذ USB-C + منفذ USB-A + مدخل سماعة 3.5mm + منفذ الشحن المغناطيسي Surface Connect\n- الأمان: تسجيل دخول فوري ببصمة الوجه عبر Windows Hello وشريحة أمان TPM 2.0\n- الهيكل: تصميم معدني كامل من الألمنيوم الفاخر، نحيف وخفيف مع كيبورد مضيء (Backlit)\n\nالهدايا والبرامج المثبتة مجاناً:\n✓ ويندوز 11 برو أصلي (مفعل مدى الحياة)\n✓ مايكروسوفت أوفيس أصلي (مفعل مدى الحياة)\n✓ برنامج Foxit PDF Editor (مفعل مدى الحياة)\n✓ جميع التعريفات والبرامج الأساسية مثبتة ومحدثة بالكامل\n\nالضمان والخدمات:\n✓ الجهاز مفحوص بالكامل وجاهز للاستخدام 100%\n✓ ضمان فحص لمدة 10 أيام (استبدال في حال وجود أي عطل فني)\n✓ ضمان خدمة وصيانة مجانية لمدة 6 أشهر\n✓ دعم فني عن بُعد عبر AnyDesk في أي وقت\n\nالشحن والدفع:\n✓ توصيل مجاني لجميع مدن المملكة العربية السعودية\n✓ الدفع عند الاستلام أو تحويل بنكي\n✓ إمكانية زيارة المعرض والمعاينة قبل الشراء\n\nالتواصل مع سمارت لايف تك (Smart Life Teck):\n🌐 الموقع الإلكتروني: www.smartlifeteck.com\n📱 كتالوج الواتساب: https://wa.me/c/966553760854\n📞 الواتساب: +966553760854\n📧 البريد الإلكتروني: smartlifeteck@gmail.com\n📱 وسائل التواصل الاجتماعي: @smartlifeteck\n━━━━━━━━━━━━━━━━━━━━━━", "photos_url": "https://drive.google.com/drive/folders/1ZMvcX-DcGK6ii5Mwp6d3yGhb6mL1RmU2", "video_url": "https://www.youtube.com/watch?v=ukc-hA6017w", "zip_url": "https://drive.google.com/file/d/1nzTr-4U4lKo44Yewjva-POWDEXBHL14G/view?usp=drivesdk", "product_status": "Active"}, {"sku": "SLT-002", "brand": "Microsoft", "model_name": "Surface Laptop 3", "touch_type": "Touch", "processor": "i7", "generation": "10", "ram": "16", "storage": "512GB", "display_size": "13.5\"", "graphics": "Intel Iris Plus", "software": "Windows 11 Pro, MS Office, PDF Editor", "short_spec": "Microsoft Surface Laptop 3 , i7 10th 16/512GB 13.5\" Touch  Intel Iris Plus", "purchased_qty": 6, "live_qty": 3, "stock_status_label": "In Stock (3)", "unit_cost": 0.0, "shipping_cost": 0.0, "landed_cost": 0.0, "total_bill": 0.0, "retail_price": 1650.0, "discount": 100.0, "final_price": 1550.0, "regular_price": 3800.0, "supplier_name": "Authorized Supplier", "purchase_date": "2026-10-14", "long_spec": "Microsoft Surface Laptop 3 (13.5\")\n\nQuick Specifications\n\n✓ Intel Core i7 10th Gen (i7-1065G7)\n\n✓ 16 GB LPDDR4x\n\n✓ 512 GB NVMe SSD\n\n✓ 13.5\" PixelSense 10-Point Multi-Touch Display (2256 x 1504)\n\n✓ Intel Iris Plus Graphics\n\n✓ USB-C, USB-A, Surface Connect, 3.5mm Headphone Jack\n\n✓ Ultra-slim aluminum design, 10-point touchscreen & Windows Hello facial recognition\n\n✓ Condition: Excellent A+\n\n✓ Windows 11 Pro with Microsoft Office Life Time activated\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nLaptop Model:\nMicrosoft Surface Laptop 3 13.5\"\n\nSpecifications:\n\n* Processor:\nIntel Core i7-1065G7 (10th Gen, 1.30 GHz base frequency, up to 3.90 GHz boost speed, 4 cores, 8 threads, 8 MB Intel Smart Cache)\n\n\n* RAM:\n16 GB LPDDR4x 3733 MT/s (On-board / Non-upgradable)\n\n\n* Storage:\n512 GB PCIe NVMe M.2 SSD\n\n\n* Display:\n13.5\" PixelSense Display, 2256 x 1504 resolution (201 PPI), 3:2 aspect ratio, 10-point multi-touch, Surface Pen enabled, Gorilla Glass\n* Graphics:\nIntel Iris Plus Graphics (Integrated)\n* Wireless:\nWi-Fi 6 (802.11ax compatible) + Bluetooth 5.0 Wireless technology\n* Webcam:\n720p HD f/2.0 camera with Windows Hello IR face authentication\n* Audio:\nOmnisonic Speakers with Dolby Audio, Dual far-field Studio Mics\n* Ports:\n1 x USB-C, 1 x USB-A, 1 x Surface Connect port, 1 x 3.5 mm headphone jack\n* Battery:\nExcellent\n* Condition:\nExcellent A+\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nKey Features:\n\n✓ 10th Gen Intel Core i7 Processor — 4-core, 8-thread power for heavy multitasking & business productivity\n✓ 13.5\" PixelSense Touchscreen — Vibrant high-resolution 2256 x 1504 display with 10-point multi-touch response\n✓ 3:2 Aspect Ratio Screen — Extra vertical screen space ideal for viewing documents, spreadsheets, and web pages\n✓ Premium Aluminum Build — Ultra-slim, sleek, and durable premium design crafted for portability\n✓ Instant-On & Fast Charging — Quickly wake up from sleep and recharge up to 80% in about an hour\n✓ Windows Hello Facial Recognition — Instant, highly secure password-free login via IR front camera\n✓ Omnisonic Audio — Hidden speakers tuned with Dolby Audio for crisp, immersive sound delivery\n✓ Dual Far-Field Studio Mics — Smart background noise reduction for crystal-clear video calls and meetings\n✓ Wi-Fi 6 Connectivity — Next-generation ultra-fast wireless speed and stable network performance\n✓ USB-C & USB-A Versatility — Easy connectivity for external displays, docking stations, and older legacy peripherals\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSoftware Package (Included Free):\n\n✓ Windows 11 Pro — Genuine | Lifetime Activated\n✓ Microsoft Office — Genuine | Lifetime Activated\n✓ Foxit PDF Editor — Lifetime Activated\n✓ All Essential Drivers Installed\n✓ Windows Optimized for Best Performance\n✓ Ready for Work, Study, Business & Online Meetings\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nQuality Assurance & Warranty:\n\n✓ Every laptop is fully inspected — hardware, software, and all components tested before delivery\n\n✓ Latest Windows updates, drivers, and optimizations installed before shipping\n\n✓ No known issues or faults — clean, reliable, and ready to use from day one\n\n✓ 10 Days Checking Warranty — If any hardware issue is found within 10 days, laptop will be replaced with another available model\n\n✓ 6 Months Service Warranty — Free servicing and technical support for 6 months after purchase\n\n✓ Free Remote Software Support via AnyDesk — anytime you need it\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nDelivery & Payment:\n\n✓ Free Delivery Across Saudi Arabia\n✓ Cash On Delivery Available\n✓ Bank Transfer Accepted\n✓ Visit our branch to inspect before purchase\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSmart Life Teck Contacts:\n\n🌐 Website: https://smartlifeteck.com/\n📱 WhatsApp Catalog: https://wa.me/c/966553760854\n📞 WhatsApp: +966553760854\n📧 Email: smartlifeteck@gmail.com\n📱 @smartlifeteck on all social media platforms\n\n━━━━━━━━━━━━━━━━━━━━━━Laptop Model:\nMicrosoft Surface Laptop 3 (13.5\" Touch) — 10th Gen Intel Core i7 | 16GB RAM | 512GB SSD\n\nSpecifications:\n- Processor:\n10th Gen Intel® Core™ i7-1065G7 Quad-Core Processor (1.30 GHz base, up to 3.90 GHz Turbo, 8MB Cache)\n\n- RAM:\n16GB LPDDR4x High-Speed RAM (3733 MHz)\n\n- Storage:\n512GB Removable M.2 NVMe Solid-State Drive (SSD)\n\n- Display:\n13.5\" PixelSense™ Touchscreen Display (2256 x 1504 resolution, 201 PPI, 3:2 Aspect Ratio, 10-Point Multi-Touch)\n\n- Graphics:\nIntel® Iris™ Plus Graphics\n\n- Wireless:\nWi-Fi 6 (802.11ax compatible) + Bluetooth® Wireless 5.0\n\n- Webcam:\n720p HD f2.0 Front-Facing Camera + Windows Hello Face Authentication Camera\n\n- Audio:\nDual Far-Field Studio Mics + Omnisonic Speakers with Dolby® Audio™\n\n- Ports:\n1 x USB-C®, 1 x USB-A, 3.5mm Headphone Jack, 1 x Surface Connect Port\n\n- Battery:\nUp to 11.5 hours typical device usage with Fast Charging support\n\n- Condition:\nExcellent A+\n\n━━━━━━━━━━━━━━━━━━━━━━\nKey Features:\n✓ Large 512GB NVMe SSD storage paired with 16GB RAM and 10th Gen Core i7\n✓ Ultra-slim premium aluminum design (approx. 1.26 kg)\n✓ Vibrant 13.5\" PixelSense™ 2K Touchscreen with 3:2 productivity aspect ratio\n✓ Windows Hello Face Authentication for fast, secure login\n✓ Backlit keyboard and precision glass trackpad\n✓ Omnisonic speakers with Dolby® Audio™ and Dual Far-Field Studio Mics\n✓ Wi-Fi 6 and USB-C connectivity for modern workflows\n✓ Fast Charging support (up to 80% charge in roughly 1 hour)\n\n━━━━━━━━━━━━━━━━━━━━━━\nSoftware Package (Included Free):\n✓ Windows 11 Pro — Genuine | Lifetime Activated\n✓ Microsoft Office — Genuine | Lifetime Activated\n✓ Foxit PDF Editor — Lifetime Activated\n✓ All Essential Drivers Installed\n✓ Windows Optimized for Best Performance\n✓ Ready for Work, Study, Business & Online Meetings\n\n━━━━━━━━━━━━━━━━━━━━━━\nQuality Assurance & Warranty:\n✓ Every laptop is fully inspected — hardware, software, and all components tested before delivery\n✓ Latest Windows updates, drivers, and optimizations installed before shipping\n✓ No known issues or faults — clean, reliable, and ready to use from day one\n✓ 10 Days Checking Warranty — If any hardware issue is found within 10 days, laptop will be replaced with another available model\n✓ 6 Months Service Warranty — Free servicing and technical support for 6 months after purchase\n✓ Free Remote Software Support via AnyDesk — anytime you need it\n\n━━━━━━━━━━━━━━━━━━━━━━\nDelivery & Payment:\n✓ Free Delivery Across Saudi Arabia\n✓ Cash On Delivery Available\n✓ Bank Transfer Accepted\n✓ Visit our branch to inspect before purchase\n\n━━━━━━━━━━━━━━━━━━━━━━\nSmart Life Teck Contacts:\n🌐 Website: www.smartlifeteck.com\n📱 WhatsApp Catalog: https://wa.me/c/966553760854\n📞 WhatsApp: +966553760854\n📧 Email: smartlifeteck@gmail.com\n📱 @smartlifeteck on all social media platforms\n━━━━━━━━━━━━━━━━━━━━━━", "arabic_spec": "━━━━━━━━━━━━━━━━━━━━━\nلابتوب مايكروسوفت سيرفس لابتوب Microsoft Surface Laptop 3 (512GB SSD)\n\nمواصفات سريعة:\n- المعالج: Intel Core i7-1065G7 (الجيل العاشر)\n- الذاكرة العشوائية (RAM): 16 جيجابايت LPDDR4x عالي السرعة\n- التخزين: 512 جيجابايت NVMe SSD مساحة كبيرة\n- الشاشة: 13.5 بوصة لمس PixelSense بدقة 2K (2256x1504)\n- كرت الشاشة: Intel Iris Plus Graphics\n- الوزن: خفيف جداً (1.26 كجم فقط)\n- الحالة: ممتاز جداً A+ (مستعمل بحالة الجديد)\n\nالمواصفات التفصيلية:\n- المعالج: Intel Core i7-1065G7 رباعي النواة (8 مسارات) بتردد يصل إلى 3.90GHz وذاكرة كاش 8MB\n- الرام: 16GB LPDDR4x بتردد 3733MHz لتشغيل البرامج الثقيلة بسلاسة\n- التخزين: 512GB M.2 NVMe SSD يوفر سرعة إقلاع فائقة ومساحة تخزين واسعة للملفات والمشاريع\n- الشاشة: 13.5 بوصة لمس PixelSense بدقة 2256x1504 وكثافة 201 بكسل لكل بوصة وتدعم قلم Surface\n- الكاميرا: كاميرا أمامية 720p HD مثالية للاجتماعات والدراسة عن بُعد مع مستشعر IR\n- الصوت: نظام صوتي Omnisonic مع Dolby Audio ومايكروفونات Studio Mics مزدوجة لعزل الضوضاء\n- المنافذ: منفذ USB-C + منفذ USB-A + منفذ سماعات 3.5mm + منفذ Surface Connect للشحن السريع\n- الأمان: فتح القفل ببصمة الوجه Windows Hello Face Sign-in مع شريحة TPM 2.0\n- الهيكل: هيكل ألمنيوم أنيق وفائق النحافة مع لوحة مفاتيح مضيئة وتراكباد زجاجي واسع\n\nالهدايا والبرامج المثبتة مجاناً:\n✓ ويندوز 11 برو أصلي (مفعل مدى الحياة)\n✓ مايكروسوفت أوفيس أصلي (مفعل مدى الحياة)\n✓ برنامج Foxit PDF Editor (مفعل مدى الحياة)\n✓ جميع التعريفات والبرامج الأساسية مثبتة ومحدثة بالكامل\n\nالضمان والخدمات:\n✓ الجهاز مفحوص بالكامل وجاهز للاستخدام 100%\n✓ ضمان فحص لمدة 10 أيام (استبدال في حال وجود أي عطل فني)\n✓ ضمان خدمة وصيانة مجانية لمدة 6 أشهر\n✓ دعم فني عن بُعد عبر AnyDesk في أي وقت\n\nالشحن والدفع:\n✓ توصيل مجاني لجميع مدن المملكة العربية السعودية\n✓ الدفع عند الاستلام أو تحويل بنكي\n✓ إمكانية زيارة المعرض والمعاينة قبل الشراء\n\nالتواصل مع سمارت لايف تك (Smart Life Teck):\n🌐 الموقع الإلكتروني: www.smartlifeteck.com\n📱 كتالوج الواتساب: https://wa.me/c/966553760854\n📞 الواتساب: +966553760854\n📧 البريد الإلكتروني: smartlifeteck@gmail.com\n📱 وسائل التواصل الاجتماعي: @smartlifeteck\n━━━━━━━━━━━━━━━━━━━━━━", "photos_url": "https://drive.google.com/drive/folders/1H32lgE9wijCIHBXN9906vFvsr6kiVgxI", "video_url": "https://www.youtube.com/watch?v=8wuIXSKbPJY", "zip_url": "https://drive.google.com/file/d/1h-XNBMHX0Navy15IZAGmcNIrm82JILuc/view?usp=drivesdk", "product_status": "Active"}, {"sku": "SLT-003", "brand": "Microsoft", "model_name": "Surface Laptop 4", "touch_type": "Touch", "processor": "AMD Ryzen 5", "generation": "11", "ram": "16", "storage": "256GB", "display_size": "13.5\"", "graphics": "AMD Radeon Graphics", "software": "Windows 11 Pro, MS Office, PDF Editor", "short_spec": "Microsoft Surface Laptop 4 , AMD Ryzen 5 11th 16/256GB 13.5\" Touch  AMD Radeon Graphics", "purchased_qty": 5, "live_qty": 4, "stock_status_label": "In Stock (4)", "unit_cost": 0.0, "shipping_cost": 0.0, "landed_cost": 0.0, "total_bill": 0.0, "retail_price": 1750.0, "discount": 100.0, "final_price": 1650.0, "regular_price": 4250.0, "supplier_name": "Authorized Supplier", "purchase_date": "2026-10-14", "long_spec": "Microsoft Surface Laptop 4 (13.5\")\n\nQuick Specifications\n\n✓ AMD Ryzen 5 Microsoft Surface Edition (Ryzen 5 4680U)\n\n✓ 16 GB LPDDR4x\n\n✓ 256 GB NVMe SSD\n\n✓ 13.5\" PixelSense 10-Point Multi-Touch Display (2256 x 1504)\n\n✓ AMD Radeon Graphics\n\n✓ USB-C, USB-A, Surface Connect, 3.5mm Headphone Jack\n\n✓ Ultra-slim premium aluminum body, up to 19 hours battery life & Windows Hello facial recognition\n\n✓ Condition: Excellent A+\n\n✓ Windows 11 Pro with Microsoft Office Life Time activated\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nLaptop Model:\nMicrosoft Surface Laptop 4 13.5\" (AMD Edition)\n\nSpecifications:\n\n* Processor:\nAMD Ryzen 5 4680U Microsoft Surface Edition (2.20 GHz base frequency, up to 4.00 GHz boost speed, 6 cores, 12 threads, 8 MB L3 cache)\n\n\n* RAM:\n16 GB LPDDR4x 4266 MT/s (On-board / Non-upgradable)\n\n\n* Storage:\n256 GB PCIe NVMe M.2 SSD\n\n\n* Display:\n13.5\" PixelSense Display, 2256 x 1504 resolution (201 PPI), 3:2 aspect ratio, 10-point multi-touch, Surface Pen enabled, Gorilla Glass 3\n* Graphics:\nAMD Radeon Graphics (Integrated)\n\n\n* Wireless:\nWi-Fi 6 (802.11ax compatible) + Bluetooth 5.0 Wireless technology\n* Webcam:\n720p HD f/2.0 camera with Windows Hello IR face authentication\n* Audio:\nOmnisonic Speakers with Dolby Atmos, Dual far-field Studio Mics\n* Ports:\n1 x USB-C, 1 x USB-A, 1 x Surface Connect port, 1 x 3.5 mm headphone jack\n* Battery:\nExcellent\n* Condition:\nExcellent A+\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nKey Features:\n\n✓ Custom AMD Ryzen 5 Surface Edition Processor — 6 cores and 12 threads deliver smooth, ultra-responsive performance\n✓ 13.5\" PixelSense Touchscreen — Ultra-sharp 2256 x 1504 resolution with fluid 10-point touch interaction\n✓ 3:2 Aspect Ratio Display — Displays 18% more vertical content than standard widescreen laptops for enhanced productivity\n✓ Premium Aluminum Build — Sleek, ultra-portable, and lightweight design engineered for mobile professionals\n✓ Exceptional Battery Life — Efficient 7nm AMD architecture provides up to 19 hours of battery usage\n✓ Dolby Atmos Omnisonic Speakers — Cinematic, spatial sound experience hidden discreetly beneath the keyboard\n✓ Dual Far-Field Studio Mics — Captures natural voice clarity while reducing background sound on video calls\n✓ Windows Hello IR Camera — Instant, secure hands-free facial recognition login\n✓ Fast Charging Support — Charges up to 80% in about one hour via the included Surface Connect power adapter\n✓ Versatile Connectivity — Includes USB-C and USB-A ports for seamless connection to all accessories and displays\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSoftware Package (Included Free):\n\n✓ Windows 11 Pro — Genuine | Lifetime Activated\n✓ Microsoft Office — Genuine | Lifetime Activated\n✓ Foxit PDF Editor — Lifetime Activated\n✓ All Essential Drivers Installed\n✓ Windows Optimized for Best Performance\n✓ Ready for Work, Study, Business & Online Meetings\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nQuality Assurance & Warranty:\n\n✓ Every laptop is fully inspected — hardware, software, and all components tested before delivery\n\n✓ Latest Windows updates, drivers, and optimizations installed before shipping\n\n✓ No known issues or faults — clean, reliable, and ready to use from day one\n\n✓ 10 Days Checking Warranty — If any hardware issue is found within 10 days, laptop will be replaced with another available model\n\n✓ 6 Months Service Warranty — Free servicing and technical support for 6 months after purchase\n\n✓ Free Remote Software Support via AnyDesk — anytime you need it\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nDelivery & Payment:\n\n✓ Free Delivery Across Saudi Arabia\n✓ Cash On Delivery Available\n✓ Bank Transfer Accepted\n✓ Visit our branch to inspect before purchase\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSmart Life Teck Contacts:\n\n🌐 Website: https://smartlifeteck.com/\n📱 WhatsApp Catalog: https://wa.me/c/966553760854\n📞 WhatsApp: +966553760854\n📧 Email: smartlifeteck@gmail.com\n📱 @smartlifeteck on all social media platforms\n\n━━━━━━━━━━━━━━━━━━━━━━", "arabic_spec": "━━━━━━━━━━━━━━━━━━━━━\nلابتوب مايكروسوفت سيرفس لابتوب Microsoft Surface Laptop 4 (13.5\" Touch)\n\nمواصفات سريعة:\n- المعالج: AMD Ryzen 5 4680U Microsoft Surface Edition (6 أنوية / 12 مسار)\n- الذاكرة العشوائية (RAM): 16 جيجابايت LPDDR4x عالي السرعة\n- التخزين: 256 جيجابايت NVMe SSD\n- الشاشة: 13.5 بوصة لمس PixelSense بدقة 2K (2256x1504)\n- كرت الشاشة: AMD Radeon Graphics (Surface Edition)\n- الوزن: خفيف جداً (1.26 كجم فقط)\n- الحالة: ممتاز جداً A+ (مستعمل بحالة الجديد)\n\nالمواصفات التفصيلية:\n- المعالج: AMD Ryzen 5 4680U سداسي النواة (12 Threads) مطور خصيصاً لأجهزة Surface بسرعة تصل إلى 4.0GHz\n- الرام: 16GB LPDDR4x لأداء احترافي في الأعمال المتعددة وبرامج الأوفيس والتصميم الخفيف\n- التخزين: 256GB M.2 NVMe SSD سريع جداً وقابل للترقية\n- الشاشة: 13.5 بوصة لمس PixelSense بدقة 2K وألوان غنية وأبعاد 3:2 وتدعم Surface Pen\n- الكاميرا: كاميرا أمامية 720p HD f2.0 عالية الوضوح مع دعم تقنية التعرف على الوجه\n- الصوت: سماعات Omnisonic بتقنية الصوت المحيطي Dolby Atmos ومايكروفونات Studio Mics مزدوجة\n- المنافذ: منفذ USB-C + منفذ USB-A + مدخل سماعة 3.5mm + منفذ الشحن السريع Surface Connect\n- الأمان: بصمة الوجه Windows Hello للدخول السريع والآمن مع تشفير TPM 2.0\n- الهيكل: هيكل معدني فاخر ومتين بوزن خفيف وكيبورد مضيء مريح للكتابة الطويلة وبطارية تدوم حتى 19 ساعة\n\nالهدايا والبرامج المثبتة مجاناً:\n✓ ويندوز 11 برو أصلي (مفعل مدى الحياة)\n✓ مايكروسوفت أوفيس أصلي (مفعل مدى الحياة)\n✓ برنامج Foxit PDF Editor (مفعل مدى الحياة)\n✓ جميع التعريفات والبرامج الأساسية مثبتة ومحدثة بالكامل\n\nالضمان والخدمات:\n✓ الجهاز مفحوص بالكامل وجاهز للاستخدام 100%\n✓ ضمان فحص لمدة 10 أيام (استبدال في حال وجود أي عطل فني)\n✓ ضمان خدمة وصيانة مجانية لمدة 6 أشهر\n✓ دعم فني عن بُعد عبر AnyDesk في أي وقت\n\nالشحن والدفع:\n✓ توصيل مجاني لجميع مدن المملكة العربية السعودية\n✓ الدفع عند الاستلام أو تحويل بنكي\n✓ إمكانية زيارة المعرض والمعاينة قبل الشراء\n\nالتواصل مع سمارت لايف تك (Smart Life Teck):\n🌐 الموقع الإلكتروني: www.smartlifeteck.com\n📱 كتالوج الواتساب: https://wa.me/c/966553760854\n📞 الواتساب: +966553760854\n📧 البريد الإلكتروني: smartlifeteck@gmail.com\n📱 وسائل التواصل الاجتماعي: @smartlifeteck\n━━━━━━━━━━━━━━━━━━━━━━", "photos_url": "https://drive.google.com/drive/folders/1InOfx5yaO0DyYKZsLNwzN9K0mpLvdB3p", "video_url": "https://www.youtube.com/watch?v=ahCZDSTHCjc", "zip_url": "https://drive.google.com/file/d/1gUh7iXow3-t_aTzLcYMGvLKQmxnVO-RW/view?usp=drivesdk", "product_status": "Active"}, {"sku": "SLT-004", "brand": "Microsoft", "model_name": "Surface Laptop GO 2", "touch_type": "Touch", "processor": "i5", "generation": "11", "ram": "8", "storage": "256GB", "display_size": "12.3\"", "graphics": "Intel Iris Xe", "software": "Windows 11 Pro, MS Office, PDF Editor", "short_spec": "Microsoft Surface Laptop GO 2 , i5 11th 8/256GB 12.3\" Touch  Intel Iris Xe", "purchased_qty": 2, "live_qty": 2, "stock_status_label": "Low Stock (2)", "unit_cost": 0.0, "shipping_cost": 0.0, "landed_cost": 0.0, "total_bill": 0.0, "retail_price": 1350.0, "discount": 50.0, "final_price": 1300.0, "regular_price": 2875.0, "supplier_name": "Authorized Supplier", "purchase_date": "2026-10-14", "long_spec": "Microsoft Surface Laptop Go 2 (12.4\")\n\nQuick Specifications\n\n✓ Intel Core i5 11th Gen (i5-1135G7)\n\n✓ 8 GB LPDDR4x\n\n✓ 256 GB NVMe SSD\n\n✓ 12.4\" PixelSense 10-Point Multi-Touch Display (1536 x 1024)\n\n✓ Intel Iris Xe Graphics\n\n✓ USB-C, USB-A, Surface Connect, 3.5mm Headphone Jack\n\n✓ Ultra-lightweight 2.48 lbs design, One Touch Fingerprint Power Button & 3:2 touchscreen display\n\n✓ Condition: Excellent A+\n\n✓ Windows 11 Pro with Microsoft Office Life Time activated\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nLaptop Model:\nMicrosoft Surface Laptop Go 2 12.4\"\n\nSpecifications:\n\n* Processor:\nIntel Core i5-1135G7 (11th Gen, 2.40 GHz base frequency, up to 4.20 GHz boost speed, 4 cores, 8 threads, 8 MB Intel Smart Cache)\n\n\n* RAM:\n8 GB LPDDR4x 4267 MT/s (On-board / Non-upgradable)\n\n\n* Storage:\n256 GB PCIe NVMe M.2 SSD\n\n\n* Display:\n12.4\" PixelSense Display, 1536 x 1024 resolution (148 PPI), 3:2 aspect ratio, 330 nits brightness, 10-point multi-touch display\n* Graphics:\nIntel Iris Xe Graphics (Integrated)\n\n\n* Wireless:\nWi-Fi 6 (802.11ax compatible) + Bluetooth 5.1 Wireless technology\n* Webcam:\n720p HD f/2.0 front-facing camera\n* Audio:\nOmnisonic Speakers with Dolby Audio Premium, Dual far-field Studio Mics\n* Ports:\n1 x USB-C, 1 x USB-A, 1 x Surface Connect port, 1 x 3.5 mm headphone jack\n* Battery:\nExcellent\n* Condition:\nExcellent A+\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nKey Features:\n\n✓ 11th Gen Intel Core i5 Processor — Quad-core, 8-thread processor providing fast, reliable daily productivity\n✓ 12.4\" PixelSense Touchscreen — Vibrant, compact multi-touch display for intuitive control and navigation\n✓ 3:2 Aspect Ratio Screen — Optimized screen orientation that displays more vertical content for reading and browsing\n✓ Featherlight & Portable — Weighs only 2.48 lbs (1.12 kg), making it extraordinarily easy to carry anywhere\n✓ One Touch Sign-In — Integrated Fingerprint Power Button with Windows Hello for quick, password-free security\n✓ Premium Dual-Material Build — Sleek aluminum top paired with a durable composite resin base\n✓ Omnisonic Audio Speakers — Discrete internal speakers delivering rich Dolby Audio Premium acoustics\n✓ Dual Far-Field Studio Mics — Smart audio pickup for clear video calls and online meetings\n✓ Next-Gen Wi-Fi 6 — Lightning-fast wireless connectivity for fast browsing and streaming speeds\n✓ Fast Charging Capability — Quickly recharges up to 80% battery capacity in about an hour\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSoftware Package (Included Free):\n\n✓ Windows 11 Pro — Genuine | Lifetime Activated\n✓ Microsoft Office — Genuine | Lifetime Activated\n✓ Foxit PDF Editor — Lifetime Activated\n✓ All Essential Drivers Installed\n✓ Windows Optimized for Best Performance\n✓ Ready for Work, Study, Business & Online Meetings\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nQuality Assurance & Warranty:\n\n✓ Every laptop is fully inspected — hardware, software, and all components tested before delivery\n\n✓ Latest Windows updates, drivers, and optimizations installed before shipping\n\n✓ No known issues or faults — clean, reliable, and ready to use from day one\n\n✓ 10 Days Checking Warranty — If any hardware issue is found within 10 days, laptop will be replaced with another available model\n\n✓ 6 Months Service Warranty — Free servicing and technical support for 6 months after purchase\n\n✓ Free Remote Software Support via AnyDesk — anytime you need it\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nDelivery & Payment:\n\n✓ Free Delivery Across Saudi Arabia\n✓ Cash On Delivery Available\n✓ Bank Transfer Accepted\n✓ Visit our branch to inspect before purchase\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSmart Life Teck Contacts:\n\n🌐 Website: https://smartlifeteck.com/\n📱 WhatsApp Catalog: https://wa.me/c/966553760854\n📞 WhatsApp: +966553760854\n📧 Email: smartlifeteck@gmail.com\n📱 @smartlifeteck on all social media platforms\n\n━━━━━━━━━━━━━━━━━━━━━━", "arabic_spec": "━━━━━━━━━━━━━━━━━━━━━\nلابتوب مايكروسوفت سيرفس لابتوب جو Microsoft Surface Laptop Go (12th Gen)\n\nمواصفات سريعة:\n- المعالج: Intel Core i5 (الجيل الثاني عشر - 12th Gen)\n- الذاكرة العشوائية (RAM): 8 جيجابايت عالي السرعة\n- التخزين: 256 جيجابايت NVMe SSD\n- الشاشة: 12.3 / 12.4 بوصة لمس PixelSense (1536x1024)\n- كرت الشاشة: Intel Iris Xe Graphics\n- الوزن: فائق الخفة (1.13 كجم فقط)\n- الحالة: ممتاز جداً A+ (مستعمل بحالة الجديد)\n\nالمواصفات التفصيلية:\n- المعالج: Intel Core i5-1235U الجيل الثاني عشر (10 أنوية) بسرعة تصل إلى 4.40GHz وذاكرة كاش 12MB\n- الرام: 8GB LPDDR5 عالي السرعة ومناسب للدراسة والأعمال المكتبية والتصفح السريع\n- التخزين: 256GB SSD سريع للإقلاع الفوري وتشغيل التطبيقات في ثوانٍ\n- الشاشة: شاشة لمس PixelSense بحجم 12.3/12.4 بوصة وأبعاد 3:2 المميزة للقراءة والعمل\n- الكاميرا: كاميرا أمامية 720p HD f2.0 عالية النقاء للاجتماعات والمحاضرات الإلكترونية\n- الصوت: سماعات Omnisonic مدمجة بتقنية Dolby Audio Premium مع مايكروفونات Studio Mics مزدوجة\n- المنافذ: منفذ USB-C + منفذ USB-A + مدخل سماعات 3.5mm + منفذ الشحن المغناطيسي Surface Connect\n- الأمان: قارئ بصمة الإصبع مدمج في زر التشغيل (Fingerprint Power Button) مع Windows Hello\n- الهيكل: تصميم مدمج وأنيق من الألمنيوم بوزن 1.13 كجم فقط يسهل حمله في أي حقيبة للجامعة أو السفر\n\nالهدايا والبرامج المثبتة مجاناً:\n✓ ويندوز 11 برو أصلي (مفعل مدى الحياة)\n✓ مايكروسوفت أوفيس أصلي (مفعل مدى الحياة)\n✓ برنامج Foxit PDF Editor (مفعل مدى الحياة)\n✓ جميع التعريفات والبرامج الأساسية مثبتة ومحدثة بالكامل\n\nالضمان والخدمات:\n✓ الجهاز مفحوص بالكامل وجاهز للاستخدام 100%\n✓ ضمان فحص لمدة 10 أيام (استبدال في حال وجود أي عطل فني)\n✓ ضمان خدمة وصيانة مجانية لمدة 6 أشهر\n✓ دعم فني عن بُعد عبر AnyDesk في أي وقت\n\nالشحن والدفع:\n✓ توصيل مجاني لجميع مدن المملكة العربية السعودية\n✓ الدفع عند الاستلام أو تحويل بنكي\n✓ إمكانية زيارة المعرض والمعاينة قبل الشراء\n\nالتواصل مع سمارت لايف تك (Smart Life Teck):\n🌐 الموقع الإلكتروني: www.smartlifeteck.com\n📱 كتالوج الواتساب: https://wa.me/c/966553760854\n📞 الواتساب: +966553760854\n📧 البريد الإلكتروني: smartlifeteck@gmail.com\n📱 وسائل التواصل الاجتماعي: @smartlifeteck\n━━━━━━━━━━━━━━━━━━━━━━", "photos_url": "https://drive.google.com/drive/folders/1hRaAlkgZjoicEj4gL9k_EsVXlS-GnTF5", "video_url": "https://www.youtube.com/watch?v=Wwq37SzTMx0", "zip_url": "https://drive.google.com/file/d/1N8nizjq-eZWKVolq-QP0UsOCQsjE1Bow/view?usp=drivesdk", "product_status": "Active"}, {"sku": "SLT-005", "brand": "Microsoft", "model_name": "Surface Laptop GO 3", "touch_type": "Touch", "processor": "i5", "generation": "12", "ram": "8", "storage": "256GB", "display_size": "12.3\"", "graphics": "Intel Iris Xe", "software": "Windows 11 Pro, MS Office, PDF Editor", "short_spec": "Microsoft Surface Laptop GO 3 , i5 12th 8/256GB 12.3\" Touch  Intel Iris Xe", "purchased_qty": 2, "live_qty": 2, "stock_status_label": "Low Stock (2)", "unit_cost": 0.0, "shipping_cost": 0.0, "landed_cost": 0.0, "total_bill": 0.0, "retail_price": 1475.0, "discount": 50.0, "final_price": 1425.0, "regular_price": 2875.0, "supplier_name": "Authorized Supplier", "purchase_date": "2026-10-14", "long_spec": "Microsoft Surface Laptop Go 3 (12.4\")\n\nQuick Specifications\n\n✓ Intel Core i5 12th Gen (i5-1235U)\n\n✓ 8 GB LPDDR5\n\n✓ 256 GB NVMe SSD\n\n✓ 12.4\" PixelSense 10-Point Multi-Touch Display (1536 x 1024)\n\n✓ Intel Iris Xe Graphics\n\n✓ USB-C, USB-A, Surface Connect, 3.5mm Headphone Jack\n\n✓ Ultra-lightweight 2.49 lbs design, Fingerprint Power Button & 15-hour battery life\n\n✓ Condition: Excellent A+\n\n✓ Windows 11 Pro with Microsoft Office Life Time activated\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nLaptop Model:\nMicrosoft Surface Laptop Go 3 12.4\"\n\nSpecifications:\n\n* Processor:\nIntel Core i5-1235U (12th Gen, 2.50 GHz base frequency, up to 4.40 GHz boost speed, 10 cores [2 Performance + 8 Efficient], 12 threads, 12 MB Intel Smart Cache)\n\n\n* RAM:\n8 GB LPDDR5 5200 MT/s (On-board / Non-upgradable)\n\n\n* Storage:\n256 GB PCIe NVMe M.2 SSD\n\n\n* Display:\n12.4\" PixelSense Display, 1536 x 1024 resolution (148 PPI), 3:2 aspect ratio, 320 nits brightness, 10-point multi-touch display, Gorilla Glass 3\n* Graphics:\nIntel Iris Xe Graphics (Integrated)\n\n\n* Wireless:\nWi-Fi 6 (802.11ax) + Bluetooth 5.1 Wireless technology\n* Webcam:\n720p HD f/2.0 front-facing camera\n* Audio:\nOmnisonic Speakers with Dolby Audio Premium, Dual far-field Studio Mics\n* Ports:\n1 x USB-C 3.2 (supports DisplayPort & Power Delivery), 1 x USB-A 3.1, 1 x Surface Connect port, 1 x 3.5 mm headphone jack\n* Battery:\nExcellent\n* Condition:\nExcellent A+\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nKey Features:\n\n✓ 12th Gen Intel Core i5 Processor — 10-core hybrid architecture for seamless multitasking and snappy daily performance\n✓ Ultra-Fast LPDDR5 Memory — 5200 MT/s RAM speed for efficient application loading and multi-tab browsing\n✓ 12.4\" PixelSense Touchscreen — Vibrant, responsive 10-point multi-touch screen designed for intuitive interaction\n✓ 3:2 Aspect Ratio Display — Optimized screen ratio offering expanded vertical workspace for document reading and productivity\n✓ Featherlight & Highly Portable — Weighs under 2.5 lbs (1.13 kg), making it perfect for on-the-go professionals and students\n✓ One-Touch Fingerprint Sign-In — Power button integrated with Fingerprint Reader for swift, secure Windows Hello login\n✓ Long-Lasting Battery Life — Energy-efficient 12th Gen Intel platform providing up to 15 hours of battery usage\n✓ Sleek Aluminum Finish — Durable anodized aluminum top casing with polycarbonate composite resin base\n✓ Omnisonic Speakers — Discrete audio integration under the keyboard tuned with Dolby Audio Premium\n✓ USB-C & USB-A Connectivity — Versatile ports for charging, high-speed data transfers, and external display output\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSoftware Package (Included Free):\n\n✓ Windows 11 Pro — Genuine | Lifetime Activated\n✓ Microsoft Office — Genuine | Lifetime Activated\n✓ Foxit PDF Editor — Lifetime Activated\n✓ All Essential Drivers Installed\n✓ Windows Optimized for Best Performance\n✓ Ready for Work, Study, Business & Online Meetings\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nQuality Assurance & Warranty:\n\n✓ Every laptop is fully inspected — hardware, software, and all components tested before delivery\n\n✓ Latest Windows updates, drivers, and optimizations installed before shipping\n\n✓ No known issues or faults — clean, reliable, and ready to use from day one\n\n✓ 10 Days Checking Warranty — If any hardware issue is found within 10 days, laptop will be replaced with another available model\n\n✓ 6 Months Service Warranty — Free servicing and technical support for 6 months after purchase\n\n✓ Free Remote Software Support via AnyDesk — anytime you need it\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nDelivery & Payment:\n\n✓ Free Delivery Across Saudi Arabia\n✓ Cash On Delivery Available\n✓ Bank Transfer Accepted\n✓ Visit our branch to inspect before purchase\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSmart Life Teck Contacts:\n\n🌐 Website: https://smartlifeteck.com/\n📱 WhatsApp Catalog: https://wa.me/c/966553760854\n📞 WhatsApp: +966553760854\n📧 Email: smartlifeteck@gmail.com\n📱 @smartlifeteck on all social media platforms\n\n━━━━━━━━━━━━━━━━━━━━━━", "arabic_spec": "━━━━━━━━━━━━━━━━━━━━━\nلابتوب مايكروسوفت سيرفس لابتوب جو Microsoft Surface Laptop Go (12th Gen)\n\nمواصفات سريعة:\n- المعالج: Intel Core i5 (الجيل الثاني عشر - 12th Gen)\n- الذاكرة العشوائية (RAM): 8 جيجابايت عالي السرعة\n- التخزين: 256 جيجابايت NVMe SSD\n- الشاشة: 12.3 / 12.4 بوصة لمس PixelSense (1536x1024)\n- كرت الشاشة: Intel Iris Xe Graphics\n- الوزن: فائق الخفة (1.13 كجم فقط)\n- الحالة: ممتاز جداً A+ (مستعمل بحالة الجديد)\n\nالمواصفات التفصيلية:\n- المعالج: Intel Core i5-1235U الجيل الثاني عشر (10 أنوية) بسرعة تصل إلى 4.40GHz وذاكرة كاش 12MB\n- الرام: 8GB LPDDR5 عالي السرعة ومناسب للدراسة والأعمال المكتبية والتصفح السريع\n- التخزين: 256GB SSD سريع للإقلاع الفوري وتشغيل التطبيقات في ثوانٍ\n- الشاشة: شاشة لمس PixelSense بحجم 12.3/12.4 بوصة وأبعاد 3:2 المميزة للقراءة والعمل\n- الكاميرا: كاميرا أمامية 720p HD f2.0 عالية النقاء للاجتماعات والمحاضرات الإلكترونية\n- الصوت: سماعات Omnisonic مدمجة بتقنية Dolby Audio Premium مع مايكروفونات Studio Mics مزدوجة\n- المنافذ: منفذ USB-C + منفذ USB-A + مدخل سماعات 3.5mm + منفذ الشحن المغناطيسي Surface Connect\n- الأمان: قارئ بصمة الإصبع مدمج في زر التشغيل (Fingerprint Power Button) مع Windows Hello\n- الهيكل: تصميم مدمج وأنيق من الألمنيوم بوزن 1.13 كجم فقط يسهل حمله في أي حقيبة للجامعة أو السفر\n\nالهدايا والبرامج المثبتة مجاناً:\n✓ ويندوز 11 برو أصلي (مفعل مدى الحياة)\n✓ مايكروسوفت أوفيس أصلي (مفعل مدى الحياة)\n✓ برنامج Foxit PDF Editor (مفعل مدى الحياة)\n✓ جميع التعريفات والبرامج الأساسية مثبتة ومحدثة بالكامل\n\nالضمان والخدمات:\n✓ الجهاز مفحوص بالكامل وجاهز للاستخدام 100%\n✓ ضمان فحص لمدة 10 أيام (استبدال في حال وجود أي عطل فني)\n✓ ضمان خدمة وصيانة مجانية لمدة 6 أشهر\n✓ دعم فني عن بُعد عبر AnyDesk في أي وقت\n\nالشحن والدفع:\n✓ توصيل مجاني لجميع مدن المملكة العربية السعودية\n✓ الدفع عند الاستلام أو تحويل بنكي\n✓ إمكانية زيارة المعرض والمعاينة قبل الشراء\n\nالتواصل مع سمارت لايف تك (Smart Life Teck):\n🌐 الموقع الإلكتروني: www.smartlifeteck.com\n📱 كتالوج الواتساب: https://wa.me/c/966553760854\n📞 الواتساب: +966553760854\n📧 البريد الإلكتروني: smartlifeteck@gmail.com\n📱 وسائل التواصل الاجتماعي: @smartlifeteck\n━━━━━━━━━━━━━━━━━━━━━━", "photos_url": "https://drive.google.com/drive/folders/1XmrNoQds_eiotGcK1OwFwffiQG4DWTBn", "video_url": "https://www.youtube.com/watch?v=FDBQPMRjujo", "zip_url": "https://drive.google.com/file/d/1NPmgUjsXf9xz_ttdAFESP_zrkDJGygVR/view?usp=drivesdk", "product_status": "Active"}, {"sku": "SLT-006", "brand": "Microsoft", "model_name": "Surface Book 2", "touch_type": "Touch", "processor": "i5", "generation": "7", "ram": "8", "storage": "256GB", "display_size": "13.5\"", "graphics": "Intel HD Graphics", "software": "Windows 11 Pro, MS Office, PDF Editor", "short_spec": "Microsoft Surface Book 2 , i5 7th 8/256GB 13.5\" Touch  Intel HD Graphics", "purchased_qty": 4, "live_qty": 4, "stock_status_label": "In Stock (4)", "unit_cost": 0.0, "shipping_cost": 0.0, "landed_cost": 0.0, "total_bill": 0.0, "retail_price": 1350.0, "discount": 50.0, "final_price": 1300.0, "regular_price": 2775.0, "supplier_name": "Authorized Supplier", "purchase_date": "2026-10-14", "long_spec": "Microsoft Surface Book 2 (13.5\")\n\nQuick Specifications\n\n✓ Intel Core i5 7th Gen (i5-7300U)\n\n✓ 8 GB LPDDR3\n\n✓ 256 GB NVMe SSD\n\n✓ 13.5\" PixelSense 10-Point Multi-Touch Detachable Display (3000 x 2000)\n\n✓ Intel HD Graphics 620\n\n✓ 2 x USB-A 3.1, 1 x USB-C, SDXC Card Reader, 2 x Surface Connect, Headphone Jack\n\n✓ 2-in-1 Detachable 3K PixelSense display, magnesium casing & dual cameras\n\n✓ Condition: Excellent A+\n\n✓ Windows 11 Pro with Microsoft Office Life Time activated\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nLaptop Model:\nMicrosoft Surface Book 2 13.5\"\n\nSpecifications:\n\n* Processor:\nIntel Core i5-7300U (7th Gen, 2.60 GHz base frequency, up to 3.50 GHz boost speed, 2 cores, 4 threads, 3 MB Intel Smart Cache)\n\n\n* RAM:\n8 GB LPDDR3 1867 MT/s (On-board / Non-upgradable)\n\n\n* Storage:\n256 GB PCIe NVMe M.2 SSD\n\n\n* Display:\n13.5\" PixelSense Display, 3000 x 2000 resolution (267 PPI), 3:2 aspect ratio, 1600:1 contrast ratio, 10-point multi-touch detachable screen, Surface Pen enabled\n* Graphics:\nIntel HD Graphics 620 (Integrated)\n\n\n* Wireless:\nWi-Fi 5 (802.11ac compatible) + Bluetooth 4.1 Wireless technology\n* Webcam:\n5.0 MP front-facing camera with 1080p HD video & Windows Hello IR face authentication; 8.0 MP rear-facing autofocus camera with 1080p HD video\n* Audio:\nFront-facing Stereo Speakers with Dolby Audio Premium, Dual microphones\n* Ports:\n2 x USB Type-A (USB 3.1 Gen 1), 1 x USB Type-C (USB 3.1 Gen 1 with Power Delivery), 1 x Full-size SDXC card reader, 2 x Surface Connect ports (1 on tablet, 1 on base), 1 x 3.5 mm headphone jack\n* Battery:\nExcellent\n* Condition:\nExcellent A+\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nKey Features:\n\n✓ 2-in-1 Detachable Design — Screen detaches instantly to become a standalone, powerful 3K tablet\n✓ 13.5\" PixelSense 3K Display — Ultra-crisp 3000 x 2000 resolution with 10-point touch and active pen support\n✓ Premium Magnesium Casing — High-strength, ultra-durable magnesium chassis with signature Dynamic Fulcrum Hinge\n✓ Dual High-Resolution Cameras — 5 MP front camera for clear video calls and 8 MP rear camera for high-quality capture\n✓ Versatile SD Card Reader — Full-size SDXC card slot ideal for photographers and creators to transfer media quickly\n✓ Windows Hello Facial Recognition — Instant and highly secure password-free login via IR facial recognition\n✓ Versatile Connectivity — Equipped with USB-C, dual USB-A, and Surface Connect for power and expansion\n✓ Front-Facing Stereo Speakers — Clear, immersive sound with Dolby Audio Premium enhancement\n✓ Multiple Usage Modes — Easily switch between Laptop, Tablet, Studio, and View modes for maximum flexibility\n✓ Fanless Silent Cooling — Thermal architecture engineered for whisper-quiet and efficient operation\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSoftware Package (Included Free):\n\n✓ Windows 11 Pro — Genuine | Lifetime Activated\n✓ Microsoft Office — Genuine | Lifetime Activated\n✓ Foxit PDF Editor — Lifetime Activated\n✓ All Essential Drivers Installed\n✓ Windows Optimized for Best Performance\n✓ Ready for Work, Study, Business & Online Meetings\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nQuality Assurance & Warranty:\n\n✓ Every laptop is fully inspected — hardware, software, and all components tested before delivery\n\n✓ Latest Windows updates, drivers, and optimizations installed before shipping\n\n✓ No known issues or faults — clean, reliable, and ready to use from day one\n\n✓ 10 Days Checking Warranty — If any hardware issue is found within 10 days, laptop will be replaced with another available model\n\n✓ 6 Months Service Warranty — Free servicing and technical support for 6 months after purchase\n\n✓ Free Remote Software Support via AnyDesk — anytime you need it\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nDelivery & Payment:\n\n✓ Free Delivery Across Saudi Arabia\n✓ Cash On Delivery Available\n✓ Bank Transfer Accepted\n✓ Visit our branch to inspect before purchase\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSmart Life Teck Contacts:\n\n🌐 Website: https://smartlifeteck.com/\n📱 WhatsApp Catalog: https://wa.me/c/966553760854\n📞 WhatsApp: +966553760854\n📧 Email: smartlifeteck@gmail.com\n📱 @smartlifeteck on all social media platforms\n\n━━━━━━━━━━━━━━━━━━━━━━", "arabic_spec": "━━━━━━━━━━━━━━━━━━━━━\nلابتوب وتابلت 2 في 1 مايكروسوفت سيرفس بوك Microsoft Surface Book 2 (13.5\" Touch)\n\nمواصفات سريعة:\n- المعالج: Intel Core i5-7300U (الجيل السابع)\n- الذاكرة العشوائية (RAM): 8 جيجابايت LPDDR3 عالي السرعة\n- التخزين: 256 جيجابايت PCIe SSD\n- الشاشة: 13.5 بوصة لمس 3K (3000x2000) قابلة للفصل كـ تابلت\n- كرت الشاشة: Intel HD Graphics 620\n- البطارية: نظام بطاريتين (بطارية في الشاشة + بطارية في الكيبورد)\n- الحالة: ممتاز جداً A+ (مستعمل بحالة الجديد)\n\nالمواصفات التفصيلية:\n- المعالج: Intel Core i5-7300U بسرعة تصل إلى 3.50GHz Turbo لأداء مستقر وعملي\n- الرام: 8GB LPDDR3 بتردد 1866MHz\n- التخزين: 256GB PCIe SSD سريع\n- الشاشة: 13.5 بوصة PixelSense فائقة الدقة 3K (3000x2000 بكسل - 267 PPI) تدعم اللمس المتعدد وتنفصل بالكامل لتعمل كتابلت مستقل\n- الكاميرا: كاميرتان (أمامية 5MP بدقة 1080p HD + خلفية 8MP بتركيز تلقائي بدقة 1080p FHD) مع كاميرا بصمة الوجه\n- الصوت: سماعات ستيريو أمامية بتقنية Dolby Audio Premium مع مايكروفونات مزدوجة\n- المنافذ: 2x USB 3.1 Type-A + منفذ USB-C + قارئ بطاقات ذاكرة SDXC كامل الحجم + مدخل سماعة 3.5mm + منفذي Surface Connect\n- الأمان: تسجيل دخول ببصمة الوجه Windows Hello مع شريحة أمان TPM 2.0\n- الهيكل: هيكل ماغنيسيوم فاخر بمفصل مرن (Dynamic Fulcrum Hinge) وكيبورد مضيء\n\nالهدايا والبرامج المثبتة مجاناً:\n✓ ويندوز 11 برو أصلي (مفعل مدى الحياة)\n✓ مايكروسوفت أوفيس أصلي (مفعل مدى الحياة)\n✓ برنامج Foxit PDF Editor (مفعل مدى الحياة)\n✓ جميع التعريفات والبرامج الأساسية مثبتة ومحدثة بالكامل\n\nالضمان والخدمات:\n✓ الجهاز مفحوص بالكامل وجاهز للاستخدام 100%\n✓ ضمان فحص لمدة 10 أيام (استبدال في حال وجود أي عطل فني)\n✓ ضمان خدمة وصيانة مجانية لمدة 6 أشهر\n✓ دعم فني عن بُعد عبر AnyDesk في أي وقت\n\nالشحن والدفع:\n✓ توصيل مجاني لجميع مدن المملكة العربية السعودية\n✓ الدفع عند الاستلام أو تحويل بنكي\n✓ إمكانية زيارة المعرض والمعاينة قبل الشراء\n\nالتواصل مع سمارت لايف تك (Smart Life Teck):\n🌐 الموقع الإلكتروني: www.smartlifeteck.com\n📱 كتالوج الواتساب: https://wa.me/c/966553760854\n📞 الواتساب: +966553760854\n📧 البريد الإلكتروني: smartlifeteck@gmail.com\n📱 وسائل التواصل الاجتماعي: @smartlifeteck\n━━━━━━━━━━━━━━━━━━━━━━", "photos_url": "https://drive.google.com/drive/folders/16pKOGz4oypuFOL6zns4tjZayPy-V9dtX", "video_url": "https://www.youtube.com/watch?v=LdSqwqK2Dro", "zip_url": "https://drive.google.com/file/d/1IPnAYi6tflGbgEbZhPdSRBRfP1Rc6xZj/view?usp=drivesdk", "product_status": "Active"}, {"sku": "SLT-007", "brand": "Microsoft", "model_name": "Surface Book 3", "touch_type": "Touch", "processor": "i7", "generation": "10", "ram": "16", "storage": "512GB", "display_size": "13.5\"", "graphics": "NVIDIA GeForce GTX 6 GB", "software": "Windows 11 Pro, MS Office, PDF Editor", "short_spec": "Microsoft Surface Book 3 , i7 10th 16/512GB 13.5\" Touch  NVIDIA GeForce GTX 6 GB", "purchased_qty": 1, "live_qty": 0, "stock_status_label": "Out of Stock (0)", "unit_cost": 0.0, "shipping_cost": 0.0, "landed_cost": 0.0, "total_bill": 0.0, "retail_price": 2800.0, "discount": 200.0, "final_price": 2600.0, "regular_price": 6550.0, "supplier_name": "Authorized Supplier", "purchase_date": "2026-10-14", "long_spec": "━━━━━━━━━━━━━━━━━━━━━━\nLaptop Model:\nMicrosoft Surface Book 3 (13.5\" Detachable 2-in-1 Touch) — 10th Gen Intel Core i7 | 16GB RAM | 512GB SSD | NVIDIA GeForce GTX\n\nSpecifications:\n- Processor:\n10th Gen Intel® Core™ i7-1065G7 Quad-Core Processor (1.30 GHz base, up to 3.90 GHz Max Turbo, 8MB Cache)\n\n- RAM:\n16GB LPDDR4x High-Speed RAM (3733 MHz)\n\n- Storage:\n512GB PCIe NVMe Solid-State Drive (SSD)\n\n- Display:\n13.5\" PixelSense™ Detachable 2-in-1 Touchscreen Display (3000 x 2000 resolution, 267 PPI, 3:2 Aspect Ratio, 10-Point Multi-Touch)\n\n- Graphics:\nNVIDIA® GeForce® GTX Dedicated Graphics + Intel® Iris™ Plus Graphics\n\n- Wireless:\nWi-Fi 6 (802.11ax compatible) + Bluetooth® Wireless 5.0\n\n- Webcam:\nWindows Hello Face Auth Camera + 5.0MP Front Camera (1080p HD) + 8.0MP Rear Autofocus Camera (1080p Full HD)\n\n- Audio:\nDual Far-Field Studio Mics + Front-Facing Stereo Speakers with Dolby Atmos®\n\n- Ports:\n2 x USB-A (3.1 Gen 2), 1 x USB-C® (3.1 Gen 2 with PD 3.0), Full-Size SDXC Card Reader, 3.5mm Headphone Jack, 2 x Surface Connect\n\n- Battery:\nDual Battery System (Detachable Tablet + Keyboard Base) up to 15.5 hours usage\n\n- Condition:\nExcellent A+\n\n━━━━━━━━━━━━━━━━━━━━━━\nKey Features:\n✓ Flagship 2-in-1 Detachable Workstation — detachable touchscreen tablet + performance keyboard base\n✓ Dedicated NVIDIA® GeForce® GTX graphics for design, video editing, CAD & gaming\n✓ 10th Gen Core i7 processor paired with 16GB RAM and fast 512GB NVMe SSD\n✓ Ultra-sharp 13.5\" 3K (3000 x 2000) PixelSense™ Touchscreen Display\n✓ Dual batteries for extended productivity on the go\n✓ Front 5MP + Rear 8MP 1080p Full HD cameras + Windows Hello Face Authentication\n✓ Backlit keyboard, Wi-Fi 6, USB-C, and full-size SDXC card reader for creators\n✓ Dolby Atmos® stereo speakers and Dual Far-Field Studio Microphones\n\n━━━━━━━━━━━━━━━━━━━━━━\nSoftware Package (Included Free):\n✓ Windows 11 Pro — Genuine | Lifetime Activated\n✓ Microsoft Office — Genuine | Lifetime Activated\n✓ Foxit PDF Editor — Lifetime Activated\n✓ All Essential Drivers Installed\n✓ Windows Optimized for Best Performance\n✓ Ready for Work, Study, Business & Online Meetings\n\n━━━━━━━━━━━━━━━━━━━━━━\nQuality Assurance & Warranty:\n✓ Every laptop is fully inspected — hardware, software, and all components tested before delivery\n✓ Latest Windows updates, drivers, and optimizations installed before shipping\n✓ No known issues or faults — clean, reliable, and ready to use from day one\n✓ 10 Days Checking Warranty — If any hardware issue is found within 10 days, laptop will be replaced with another available model\n✓ 6 Months Service Warranty — Free servicing and technical support for 6 months after purchase\n✓ Free Remote Software Support via AnyDesk — anytime you need it\n\n━━━━━━━━━━━━━━━━━━━━━━\nDelivery & Payment:\n✓ Free Delivery Across Saudi Arabia\n✓ Cash On Delivery Available\n✓ Bank Transfer Accepted\n✓ Visit our branch to inspect before purchase\n\n━━━━━━━━━━━━━━━━━━━━━━\nSmart Life Teck Contacts:\n🌐 Website: www.smartlifeteck.com\n📱 WhatsApp Catalog: https://wa.me/c/966553760854\n📞 WhatsApp: +966553760854\n📧 Email: smartlifeteck@gmail.com\n📱 @smartlifeteck on all social media platforms\n━━━━━━━━━━━━━━━━━━━━━━", "arabic_spec": "━━━━━━━━━━━━━━━━━━━━━\nمحطة عمل وتابلت 2 في 1 مايكروسوفت سيرفس بوك Microsoft Surface Book 3 (NVIDIA GTX)\n\nمواصفات سريعة:\n- المعالج: Intel Core i7-1065G7 (الجيل العاشر)\n- الذاكرة العشوائية (RAM): 16 جيجابايت LPDDR4x عالي السرعة\n- التخزين: 512 جيجابايت PCIe NVMe SSD\n- الشاشة: 13.5 بوصة لمس 3K (3000x2000) قابلة للفصل كـ تابلت\n- كرت الشاشة: كرت منفصل NVIDIA GeForce GTX + كرت Intel Iris Plus\n- البطارية: بطاريتان مزدوجتان (في الشاشة وفي قاعدة الكيبورد)\n- الحالة: ممتاز جداً A+ (مستعمل بحالة الجديد)\n\nالمواصفات التفصيلية:\n- المعالج: Intel Core i7-1065G7 رباعي النواة (8 مسارات) بسرعة تصل إلى 3.90GHz وذاكرة كاش 8MB\n- الرام: 16GB LPDDR4x بتردد 3733MHz للمونتاج والتصميم الهندسي وتعدد المهام الثقيلة\n- التخزين: 512GB PCIe NVMe SSD فائق السرعة\n- الشاشة: 13.5 بوصة لمس PixelSense بدقة 3K (3000x2000) تنفصل بضغطة زر لتتحول إلى لوح رسم أو تابلت احترافي\n- الكاميرا: كاميرا أمامية 5MP 1080p HD + كاميرا خلفية 8MP Auto-Focus 1080p FHD + كاميرا IR\n- الصوت: سماعات ستيريو أمامية بتقنية Dolby Atmos ومايكروفونات Studio Mics مزدوجة\n- المنافذ: 2x USB-A 3.1 Gen 2 + منفذ USB-C (يدعم PD 3.0) + قارئ بطاقات SDXC كامل + مدخل سماعة 3.5mm\n- الأمان: بصمة الوجه Windows Hello مع شريحة أمان TPM 2.0 للأعمال\n- الهيكل: هيكل ماغنيسيوم احترافي 2 في 1 مع كيبورد مضيء وكرت شاشة NVIDIA منفصل مدمج في القاعدة\n\nالهدايا والبرامج المثبتة مجاناً:\n✓ ويندوز 11 برو أصلي (مفعل مدى الحياة)\n✓ مايكروسوفت أوفيس أصلي (مفعل مدى الحياة)\n✓ برنامج Foxit PDF Editor (مفعل مدى الحياة)\n✓ جميع التعريفات والبرامج الأساسية مثبتة ومحدثة بالكامل\n\nالضمان والخدمات:\n✓ الجهاز مفحوص بالكامل وجاهز للاستخدام 100%\n✓ ضمان فحص لمدة 10 أيام (استبدال في حال وجود أي عطل فني)\n✓ ضمان خدمة وصيانة مجانية لمدة 6 أشهر\n✓ دعم فني عن بُعد عبر AnyDesk في أي وقت\n\nالشحن والدفع:\n✓ توصيل مجاني لجميع مدن المملكة العربية السعودية\n✓ الدفع عند الاستلام أو تحويل بنكي\n✓ إمكانية زيارة المعرض والمعاينة قبل الشراء\n\nالتواصل مع سمارت لايف تك (Smart Life Teck):\n🌐 الموقع الإلكتروني: www.smartlifeteck.com\n📱 كتالوج الواتساب: https://wa.me/c/966553760854\n📞 الواتساب: +966553760854\n📧 البريد الإلكتروني: smartlifeteck@gmail.com\n📱 وسائل التواصل الاجتماعي: @smartlifeteck\n━━━━━━━━━━━━━━━━━━━━━━", "photos_url": "https://drive.google.com/drive/folders/10-QtuJSGwPn2Yk3R2IzkNVxH1wm5RApQ", "video_url": "https://www.youtube.com/watch?v=ZezPkXKBZus", "zip_url": "https://drive.google.com/drive/folders/10-QtuJSGwPn2Yk3R2IzkNVxH1wm5RApQ", "product_status": "Returned to Supplier"}, {"sku": "SLT-008", "brand": "Toshiba", "model_name": "Tecra", "touch_type": "Touch", "processor": "i5", "generation": "10", "ram": "8", "storage": "256GB", "display_size": "14.0\"", "graphics": "Intel UHD Graphics", "software": "Windows 11 Pro, MS Office, PDF Editor", "short_spec": "Toshiba Tecra , i5 10th 8/256GB 14.0\" Touch  Intel UHD Graphics", "purchased_qty": 5, "live_qty": 5, "stock_status_label": "In Stock (5)", "unit_cost": 0.0, "shipping_cost": 0.0, "landed_cost": 0.0, "total_bill": 0.0, "retail_price": 900.0, "discount": 100.0, "final_price": 800.0, "regular_price": 1500.0, "supplier_name": "Authorized Supplier", "purchase_date": "2026-10-14", "long_spec": "Dynabook Tecra A40-G\n\nQuick Specifications\n\n✓ Intel Core i5 10th Gen (i5-10210U)\n\n✓ 8 GB DDR4\n\n✓ 256 GB NVMe SSD\n\n✓ 14.0\" HD / Full HD Anti-Glare Display\n\n✓ Intel UHD Graphics\n\n✓ USB-C 3.2 Gen 2, 2 x USB 3.2 Gen 1, HDMI, RJ-45 Ethernet, microSD Card Reader\n\n✓ Lightweight 1.47 kg chassis, MIL-STD-810G military-grade durability testing\n\n✓ Condition: Excellent A+\n\n✓ Windows 11 Pro with Microsoft Office Life Time activated\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nLaptop Model:\nDynabook Tecra A40-G\n\nSpecifications:\n\n* Processor:\nIntel Core i5-10210U (10th Gen, 1.60 GHz base frequency, up to 4.20 GHz boost speed, 4 cores, 8 threads, 6 MB Intel Smart Cache)\n\n\n* RAM:\n8 GB DDR4 2667 MT/s (8 GB soldered on-board + 1 SO-DIMM slot open, upgradable up to 24 GB)\n\n\n* Storage:\n256 GB PCIe NVMe M.2 SSD\n\n\n* Display:\n14.0\" Anti-Glare LED Display (1366 x 768 HD or 1920 x 1080 FHD options, 16:9 aspect ratio)\n* Graphics:\nIntel UHD Graphics (Integrated)\n* Wireless:\nWi-Fi 6 (Intel AX200 802.11ax) + Bluetooth 5.0\n* Webcam:\n720p HD webcam with dual microphones & optional IR facial recognition\n* Audio:\nStereo speakers with DTS audio processing\n\n\n* Ports:\n1 x USB 3.2 Gen 2 Type-C (supports Power Delivery, DisplayPort & data transfer), 2 x USB 3.2 Gen 1 Type-A (with USB Sleep-and-Charge support), 1 x HDMI, 1 x RJ-45 Gigabit Ethernet, 1 x MicroSD card reader, 1 x 3.5 mm headphone/microphone combo jack\n* Battery:\nExcellent\n* Condition:\nExcellent A+\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nKey Features:\n\n✓ 10th Gen Intel Core i5 Processor — 4-core, 8-thread CPU offering reliable multitasking performance for everyday business tasks\n✓ MIL-STD-810G Tested — Ruggedized chassis tested to military standards to withstand daily drops, bumps, and vibration\n✓ Ultra-Portable & Lightweight — Weighs starting at just 1.47 kg (3.2 lbs) for effortless daily commute and travel\n✓ Comprehensive I/O Ports — Complete connectivity including full-size HDMI, Gigabit Ethernet RJ-45, and USB-C\n✓ Expandable DDR4 Memory — Includes 8 GB base memory with an extra slot for simple upgrades up to 24 GB RAM\n✓ Fast USB Type-C Port — Supports high-speed data transfer, external monitor output via DisplayPort, and power delivery\n✓ Next-Gen Wi-Fi 6 — Intel AX200 Wi-Fi 6 module provides ultra-fast network speeds and reliable wireless connections\n✓ Built-in MicroSD Reader — Conveniently transfer photos, video, and data files directly using the card reader slot\n✓ Enterprise Security Protection — Features proprietary Dynabook BIOS and TPM 2.0 module for robust device security\n✓ Anti-Glare Display Panel — Non-reflective 14.0-inch display panel ensures comfortable viewing in bright outdoor conditions\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSoftware Package (Included Free):\n\n✓ Windows 11 Pro — Genuine | Lifetime Activated\n✓ Microsoft Office — Genuine | Lifetime Activated\n✓ Foxit PDF Editor — Lifetime Activated\n✓ All Essential Drivers Installed\n✓ Windows Optimized for Best Performance\n✓ Ready for Work, Study, Business & Online Meetings\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nQuality Assurance & Warranty:\n\n✓ Every laptop is fully inspected — hardware, software, and all components tested before delivery\n\n✓ Latest Windows updates, drivers, and optimizations installed before shipping\n\n✓ No known issues or faults — clean, reliable, and ready to use from day one\n\n✓ 10 Days Checking Warranty — If any hardware issue is found within 10 days, laptop will be replaced with another available model\n\n✓ 6 Months Service Warranty — Free servicing and technical support for 6 months after purchase\n\n✓ Free Remote Software Support via AnyDesk — anytime you need it\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nDelivery & Payment:\n\n✓ Free Delivery Across Saudi Arabia\n✓ Cash On Delivery Available\n✓ Bank Transfer Accepted\n✓ Visit our branch to inspect before purchase\n\n━━━━━━━━━━━━━━━━━━━━━━\n\nSmart Life Teck Contacts:\n\n🌐 Website: https://smartlifeteck.com/\n📱 WhatsApp Catalog: https://wa.me/c/966553760854\n📞 WhatsApp: +966553760854\n📧 Email: smartlifeteck@gmail.com\n📱 @smartlifeteck on all social media platforms\n\n━━━━━━━━━━━━━━━━━━━━━━", "arabic_spec": "━━━━━━━━━━━━━━━━━━━━━\nلابتوب الأعمال توشيبا تيكرا Toshiba / Dynabook Tecra (14.0\" FHD Touch)\n\nمواصفات سريعة:\n- المعالج: Intel Core i5-10210U (الجيل العاشر)\n- الذاكرة العشوائية (RAM): 8 جيجابايت DDR4 عالي السرعة\n- التخزين: 256 جيجابايت M.2 NVMe SSD\n- الشاشة: 14.0 بوصة لمس Full HD (1920x1080) IPS مضادة للتوهج\n- كرت الشاشة: Intel UHD Graphics\n- الوزن: خفيف ومتين (حوالي 1.47 كجم)\n- الحالة: ممتاز جداً A+ (مستعمل بحالة الجديد)\n\nالمواصفات التفصيلية:\n- المعالج: Intel Core i5-10210U رباعي النواة (8 مسارات) بسرعة تصل إلى 4.20GHz وذاكرة كاش 6MB\n- الرام: 8GB DDR4 عالي السرعة وقابل للترقية\n- التخزين: 256GB M.2 PCIe NVMe SSD سريع جداً في تشغيل النظام والبرامج\n- الشاشة: 14.0 بوصة Full HD IPS تعمل باللمس (Touchscreen) ومضادة للانعكاس لراحة العين\n- الكاميرا: كاميرا HD مدمجة مع غطاء خصوصية منزلق (Privacy Shutter) ومايكروفون مزدوج\n- الصوت: سماعات ستيريو عالية الوضوح بتقنية DTS Audio Processing\n- المنافذ: منفذ USB-C (يدعم الشحن والعرض) + 2x USB 3.2 + منفذ HDMI كامل + مدخل شبكة LAN RJ-45 + قارئ microSD\n- الأمان: غطاء كاميرا للخصوصية، شريحة أمان TPM 2.0، ومدخل قفل أمان\n- الهيكل: هيكل أعمال متين مختبر وفق معايير المتانة العسكرية MIL-STD-810G مع كيبورد مريح ومقاوم لرذاذ السوائل\n\nالهدايا والبرامج المثبتة مجاناً:\n✓ ويندوز 11 برو أصلي (مفعل مدى الحياة)\n✓ مايكروسوفت أوفيس أصلي (مفعل مدى الحياة)\n✓ برنامج Foxit PDF Editor (مفعل مدى الحياة)\n✓ جميع التعريفات والبرامج الأساسية مثبتة ومحدثة بالكامل\n\nالضمان والخدمات:\n✓ الجهاز مفحوص بالكامل وجاهز للاستخدام 100%\n✓ ضمان فحص لمدة 10 أيام (استبدال في حال وجود أي عطل فني)\n✓ ضمان خدمة وصيانة مجانية لمدة 6 أشهر\n✓ دعم فني عن بُعد عبر AnyDesk في أي وقت\n\nالشحن والدفع:\n✓ توصيل مجاني لجميع مدن المملكة العربية السعودية\n✓ الدفع عند الاستلام أو تحويل بنكي\n✓ إمكانية زيارة المعرض والمعاينة قبل الشراء\n\nالتواصل مع سمارت لايف تك (Smart Life Teck):\n🌐 الموقع الإلكتروني: www.smartlifeteck.com\n📱 كتالوج الواتساب: https://wa.me/c/966553760854\n📞 الواتساب: +966553760854\n📧 البريد الإلكتروني: smartlifeteck@gmail.com\n📱 وسائل التواصل الاجتماعي: @smartlifeteck\n━━━━━━━━━━━━━━━━━━━━━━", "photos_url": "https://drive.google.com/drive/folders/1YgrOYIVej36I70F4G8qlxbNdDgz0CQYI", "video_url": "https://www.youtube.com/watch?v=t2_pR2wLOVI", "zip_url": "https://drive.google.com/file/d/1ob_JsGgA3NUqdskFGekv7Q1qP2ysrfOu/view?usp=drivesdk", "product_status": "Active"}], "sales": [{"order_id": "DEMO-001", "customer_name": "Sample Customer (Demo)", "customer_city": "Riyadh", "sku": "SLT-001", "short_spec": "Microsoft Surface Laptop 3 , i7 10th 16/256GB 13.5\" Touch  Intel Iris Plus", "sold_qty": 1, "unit_sold_price": 1500.0, "shipping_fee": 0.0, "total_sale": 1500.0, "amount_received": 1500.0, "remaining_amount": 0.0, "sale_date": "2026-11-15", "followup_date": "", "payment_status": "Paid", "followup_priority": "🟢 Paid / Completed", "order_status": "Completed", "sold_by": "Smart Life Teck", "sales_channel": "Website", "payment_method": "COD", "landed_cost": 0.0, "net_profit": 0.0, "notes": "Demo sample order for SLT SMART IMS open-source setup", "customer_contact": ""}]}
SLTSEEDJSON;
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : array('purchases' => array(), 'sales' => array());
    }

    public function maybe_seed_initial_data() {
        $settings = get_option(self::OPT_SETTINGS);
        if (!is_array($settings)) {
            $settings = array(
                'master_sheet_id'   => self::DEFAULT_MASTER_SHEET_ID,
                'team_sheet_id'     => self::DEFAULT_TEAM_SHEET_ID,
                'queue_sheet_id'    => self::DEFAULT_QUEUE_SHEET_ID,
                'drive_folder_id'   => self::DEFAULT_DRIVE_FOLDER_ID,
                'apps_script_url'   => '',
                'make_webhook_url'  => '',
                'secret_token'      => 'SLT-SYNC-' . strtoupper(wp_generate_password(12, false, false)),
                'team_pin'          => '',
                'admin_pin'         => '',
                'whatsapp_number'   => '966553760854',
                'auto_wc_sync'      => '1',
                'auto_sheet_sync'   => '1',
            );
            update_option(self::OPT_SETTINGS, $settings);
        }

        $inventory = get_option(self::OPT_INVENTORY);
        if (!is_array($inventory) || empty($inventory)) {
            $seed = $this->get_default_seed_data();
            $inv_map = array();
            if (!empty($seed['purchases'])) {
                foreach ($seed['purchases'] as $item) {
                    $inv_map[$item['sku']] = $item;
                }
            }
            update_option(self::OPT_INVENTORY, $inv_map);
        }

        $sales = get_option(self::OPT_SALES);
        if (!is_array($sales) || empty($sales)) {
            $seed = $this->get_default_seed_data();
            update_option(self::OPT_SALES, isset($seed['sales']) ? $seed['sales'] : array());
        }

        $queue = get_option(self::OPT_QUEUE);
        if (!is_array($queue)) {
            $default_queue = array(
                array(
                    'row_id'        => 'POST-001',
                    'schedule_time' => '2026-11-16 13:00',
                    'sku'           => 'SLT-002',
                    'video_url'     => 'https://drive.google.com/file/d/1DeSgjGdP4VmueYvRp9fwwEhdvPs0I_I1/view?usp=drivesdk',
                    'title'         => 'Microsoft Surface Laptop 3 (Core i7 10th Gen | 16GB RAM | 512GB SSD | 2K Touch) Best Deal in KSA',
                    'short_caption' => 'Microsoft Surface Laptop 3 (Core i7 10th Gen, 16GB RAM, 512GB SSD, 13.5" 2K Touch) in Excellent Grade A+ condition at Smart Life Teck! 10 Days Check + 6 Months Service Warranty, Free KSA Delivery & COD. WhatsApp: https://wa.me/966553760854 | Website: https://smartlifeteck.com/ #SmartLifeTeck #LaptopKSA #SurfaceLaptop3 #RiyadhLaptops #JeddahLaptops',
                    'full_desc'     => 'Experience ultra-slim speed and reliability with the Microsoft Surface Laptop 3 (13.5" 2K Touchscreen, 512GB NVMe SSD — SKU: SLT-002) in Excellent Grade A+ condition at Smart Life Teck! Includes 10 Days Check Warranty + 6 Months Service Warranty + Free Delivery & COD Across KSA. Order on WhatsApp: https://wa.me/966553760854',
                    'platforms'     => 'YouTube, TikTok, Facebook, Instagram, Haraj KSA',
                    'status'        => 'Ready',
                ),
                array(
                    'row_id'        => 'POST-002',
                    'schedule_time' => '2026-11-16 20:30',
                    'sku'           => 'SLT-001',
                    'video_url'     => 'https://drive.google.com/file/d/1BhJ5p-u8qcbs3I2HpGLBuXJnf4N6YdAE/view?usp=drivesdk',
                    'title'         => 'Microsoft Surface Laptop 3 Core i7 10th Gen 16GB RAM 256GB SSD 2K Touch | Only 1,500 SAR in KSA',
                    'short_caption' => 'Hot Deal! Microsoft Surface Laptop 3 (Core i7 10th Gen, 16GB RAM, 256GB SSD, 13.5" 2K Touch) in Excellent Grade A+ condition for ONLY 1,500 SAR! Free KSA Delivery & Cash on Delivery. WhatsApp: https://wa.me/966553760854 | Website: https://smartlifeteck.com/ #SmartLifeTeck #LaptopKSA #Duba #Yanbu #SurfaceLaptop3',
                    'full_desc'     => 'Upgrade your workday with the ultra-slim Microsoft Surface Laptop 3 (13.5" Touch — SKU: SLT-001) in Excellent Grade A+ condition for ONLY 1,500 SAR (Regular 3,250 SAR) at Smart Life Teck! Order via WhatsApp: https://wa.me/966553760854',
                    'platforms'     => 'YouTube, TikTok, Facebook, Instagram, Haraj KSA',
                    'status'        => 'Ready',
                ),
            );
            update_option(self::OPT_QUEUE, $default_queue);
        }
    }

    public function register_admin_menu() {
        add_menu_page(
            'SLT SMART IMS — Smart Life Teck',
            'SLT SMART IMS',
            'edit_posts',
            'slt-ims-hub',
            array($this, 'render_admin_page'),
            'dashicons-laptop',
            26
        );
    }

    // =========================================================================
    // REST API ENDPOINTS FOR 2-WAY GOOGLE SHEETS & MAKE.COM SYNC
    // =========================================================================
    public function register_rest_routes() {
        register_rest_route('slt-ims/v1', '/sync', array(
            'methods'             => array('GET', 'POST'),
            'callback'            => array($this, 'handle_rest_sync'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_rest_sync(\WP_REST_Request $request) {
        $settings = get_option(self::OPT_SETTINGS, array());
        $expected_token = isset($settings['secret_token']) ? $settings['secret_token'] : '';
        $provided_token = $request->get_param('token') ?: $request->get_header('X-SLT-Token');

        if (!empty($expected_token) && $provided_token !== $expected_token) {
            return new \WP_REST_Response(array('success' => false, 'message' => 'Invalid SLT sync token.'), 403);
        }

        if ($request->get_method() === 'GET') {
            return new \WP_REST_Response(array(
                'success'   => true,
                'inventory' => array_values(get_option(self::OPT_INVENTORY, array())),
                'sales'     => get_option(self::OPT_SALES, array()),
                'queue'     => get_option(self::OPT_QUEUE, array()),
                'timestamp' => current_time('mysql'),
            ), 200);
        }

        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_body_params();
        }
        $action = isset($payload['action']) ? sanitize_text_field($payload['action']) : 'upsert_inventory';

        if ($action === 'upsert_inventory' && !empty($payload['item'])) {
            $item = $this->sanitize_inventory_item($payload['item']);
            $inventory = get_option(self::OPT_INVENTORY, array());
            $inventory[$item['sku']] = $item;
            update_option(self::OPT_INVENTORY, $inventory);
            $this->recalculate_all_live_stock();
            if (!empty($settings['auto_wc_sync'])) {
                $this->sync_single_item_to_woocommerce($item['sku']);
            }
            return new \WP_REST_Response(array('success' => true, 'sku' => $item['sku'], 'message' => 'Inventory item synced from Google Sheets to WordPress & WooCommerce.'), 200);
        }

        if ($action === 'bulk_sync_from_sheets') {
            $count_inv = 0;
            $count_sales = 0;
            if (!empty($payload['purchases']) && is_array($payload['purchases'])) {
                $inventory = get_option(self::OPT_INVENTORY, array());
                foreach ($payload['purchases'] as $raw_item) {
                    if (empty($raw_item['sku'])) continue;
                    $item = $this->sanitize_inventory_item($raw_item);
                    $inventory[$item['sku']] = $item;
                    $count_inv++;
                }
                update_option(self::OPT_INVENTORY, $inventory);
            }
            if (!empty($payload['sales']) && is_array($payload['sales'])) {
                $sales_list = array();
                foreach ($payload['sales'] as $raw_sale) {
                    if (empty($raw_sale['order_id'])) continue;
                    $sales_list[] = $this->sanitize_sale_item($raw_sale);
                    $count_sales++;
                }
                update_option(self::OPT_SALES, $sales_list);
            }
            $this->recalculate_all_live_stock();
            if (!empty($settings['auto_wc_sync'])) {
                $this->sync_all_to_woocommerce();
            }
            return new \WP_REST_Response(array(
                'success' => true,
                'message' => "Synced {$count_inv} laptops and {$count_sales} sales from Google Sheets.",
            ), 200);
        }

        if ($action === 'upsert_sale' && !empty($payload['sale'])) {
            $sale = $this->sanitize_sale_item($payload['sale']);
            $sales = get_option(self::OPT_SALES, array());
            $found = false;
            foreach ($sales as $idx => $s) {
                if ($s['order_id'] === $sale['order_id']) {
                    $sales[$idx] = $sale;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $sales[] = $sale;
            }
            update_option(self::OPT_SALES, $sales);
            $this->recalculate_all_live_stock();
            if (!empty($settings['auto_wc_sync']) && !empty($sale['sku'])) {
                $this->sync_single_item_to_woocommerce($sale['sku']);
            }
            return new \WP_REST_Response(array('success' => true, 'order_id' => $sale['order_id']), 200);
        }

        return new \WP_REST_Response(array('success' => false, 'message' => 'Unknown sync action.'), 400);
    }

    // =========================================================================
    // DATA SANITIZATION, STOCK RECALCULATION & WOOCOMMERCE SYNC
    // =========================================================================
    public function sanitize_inventory_item($raw) {
        $sku           = strtoupper(sanitize_text_field(isset($raw['sku']) ? $raw['sku'] : ''));
        $brand         = sanitize_text_field(isset($raw['brand']) ? $raw['brand'] : 'Microsoft');
        $model_name    = sanitize_text_field(isset($raw['model_name']) ? $raw['model_name'] : '');
        $touch_type    = sanitize_text_field(isset($raw['touch_type']) ? $raw['touch_type'] : 'Touch');
        $processor     = sanitize_text_field(isset($raw['processor']) ? $raw['processor'] : 'i7');
        $generation    = sanitize_text_field(isset($raw['generation']) ? $raw['generation'] : '10');
        $ram           = sanitize_text_field(isset($raw['ram']) ? $raw['ram'] : '16');
        $storage       = sanitize_text_field(isset($raw['storage']) ? $raw['storage'] : '256GB');
        $display_size  = sanitize_text_field(isset($raw['display_size']) ? $raw['display_size'] : '13.5"');
        $graphics      = sanitize_text_field(isset($raw['graphics']) ? $raw['graphics'] : 'Intel Iris Plus');
        $software      = sanitize_text_field(isset($raw['software']) ? $raw['software'] : 'Windows 11 Pro, MS Office, PDF Editor');
        $short_spec    = sanitize_text_field(isset($raw['short_spec']) && !empty($raw['short_spec'])
            ? $raw['short_spec']
            : "{$brand} {$model_name} , {$processor} {$generation}th {$ram}/{$storage} {$display_size} {$touch_type}  {$graphics}");

        $purchased_qty = max(0, intval(isset($raw['purchased_qty']) ? $raw['purchased_qty'] : 1));
        $unit_cost     = floatval(preg_replace('/[^0-9.]/', '', strval(isset($raw['unit_cost']) ? $raw['unit_cost'] : 0)));
        $shipping_cost = floatval(preg_replace('/[^0-9.]/', '', strval(isset($raw['shipping_cost']) ? $raw['shipping_cost'] : 200)));
        $landed_cost   = $unit_cost + $shipping_cost;
        $total_bill    = $purchased_qty * $landed_cost;
        $retail_price  = floatval(preg_replace('/[^0-9.]/', '', strval(isset($raw['retail_price']) ? $raw['retail_price'] : 0)));
        $discount      = floatval(preg_replace('/[^0-9.]/', '', strval(isset($raw['discount']) ? $raw['discount'] : 0)));
        $final_price   = max(0, $retail_price - $discount);
        $regular_price = floatval(preg_replace('/[^0-9.]/', '', strval(isset($raw['regular_price']) ? $raw['regular_price'] : ($retail_price * 2))));
        $supplier_name = sanitize_text_field(isset($raw['supplier_name']) ? $raw['supplier_name'] : 'Authorized Supplier');
        $purchase_date = sanitize_text_field(isset($raw['purchase_date']) ? $raw['purchase_date'] : current_time('Y-m-d'));
        $long_spec     = isset($raw['long_spec']) ? wp_kses_post($raw['long_spec']) : '';
        $arabic_spec   = isset($raw['arabic_spec']) ? wp_kses_post($raw['arabic_spec']) : '';
        $photos_url    = esc_url_raw(isset($raw['photos_url']) ? $raw['photos_url'] : '');
        $video_url     = esc_url_raw(isset($raw['video_url']) ? $raw['video_url'] : '');
        $zip_url       = esc_url_raw(isset($raw['zip_url']) ? $raw['zip_url'] : '');
        $prod_status   = sanitize_text_field(isset($raw['product_status']) ? $raw['product_status'] : 'Active');
        $live_qty      = isset($raw['live_qty']) ? intval($raw['live_qty']) : $purchased_qty;

        return array(
            'sku'                => $sku,
            'brand'              => $brand,
            'model_name'         => $model_name,
            'touch_type'         => $touch_type,
            'processor'          => $processor,
            'generation'         => $generation,
            'ram'                => $ram,
            'storage'            => $storage,
            'display_size'       => $display_size,
            'graphics'           => $graphics,
            'software'           => $software,
            'short_spec'         => $short_spec,
            'purchased_qty'      => $purchased_qty,
            'live_qty'           => $live_qty,
            'stock_status_label' => $this->format_stock_status($live_qty, $prod_status),
            'unit_cost'          => $unit_cost,
            'shipping_cost'      => $shipping_cost,
            'landed_cost'        => $landed_cost,
            'total_bill'         => $total_bill,
            'retail_price'       => $retail_price,
            'discount'           => $discount,
            'final_price'        => $final_price,
            'regular_price'      => $regular_price,
            'supplier_name'      => $supplier_name,
            'purchase_date'      => $purchase_date,
            'long_spec'          => $long_spec,
            'arabic_spec'        => $arabic_spec,
            'photos_url'         => $photos_url,
            'video_url'          => $video_url,
            'zip_url'            => $zip_url,
            'product_status'     => $prod_status,
        );
    }

    public function sanitize_sale_item($raw) {
        $inventory = get_option(self::OPT_INVENTORY, array());
        $sku       = strtoupper(sanitize_text_field(isset($raw['sku']) ? $raw['sku'] : ''));
        $inv_item  = isset($inventory[$sku]) ? $inventory[$sku] : null;

        $order_id        = sanitize_text_field(isset($raw['order_id']) && !empty($raw['order_id']) ? $raw['order_id'] : 'SLT-' . wp_rand(100, 999));
        $customer_name   = sanitize_text_field(isset($raw['customer_name']) ? $raw['customer_name'] : 'Walk-in Customer');
        $customer_city   = sanitize_text_field(isset($raw['customer_city']) ? $raw['customer_city'] : 'Duba');
        $short_spec      = $inv_item ? $inv_item['short_spec'] : sanitize_text_field(isset($raw['short_spec']) ? $raw['short_spec'] : '');
        $sold_qty        = max(1, intval(isset($raw['sold_qty']) ? $raw['sold_qty'] : 1));
        $unit_sold_price = floatval(preg_replace('/[^0-9.]/', '', strval(isset($raw['unit_sold_price']) ? $raw['unit_sold_price'] : ($inv_item ? $inv_item['final_price'] : 0))));
        $shipping_fee    = floatval(preg_replace('/[^0-9.]/', '', strval(isset($raw['shipping_fee']) ? $raw['shipping_fee'] : 0)));
        $order_status    = sanitize_text_field(isset($raw['order_status']) ? $raw['order_status'] : 'Completed');
        $total_sale      = in_array($order_status, array('Cancelled', 'Returned'), true) ? 0 : ($sold_qty * $unit_sold_price);
        $amount_received = floatval(preg_replace('/[^0-9.]/', '', strval(isset($raw['amount_received']) ? $raw['amount_received'] : $total_sale)));
        $remaining       = max(0, $total_sale - $amount_received);
        $sale_date       = sanitize_text_field(isset($raw['sale_date']) && !empty($raw['sale_date']) ? $raw['sale_date'] : current_time('Y-m-d'));
        $followup_date   = $remaining > 0 ? date('Y-m-d', strtotime($sale_date . ' +30 days')) : '';
        $payment_status  = $remaining <= 0 ? 'Paid' : ($amount_received > 0 ? 'Partial' : 'Pending');
        $priority        = $payment_status === 'Paid' ? '🟢 Paid / Completed' : '🟡 Due Soon';
        $sold_by         = sanitize_text_field(isset($raw['sold_by']) ? $raw['sold_by'] : 'Shahzad');
        $sales_channel   = sanitize_text_field(isset($raw['sales_channel']) ? $raw['sales_channel'] : 'WhatsApp');
        $payment_method  = sanitize_text_field(isset($raw['payment_method']) ? $raw['payment_method'] : 'COD');
        $unit_landed     = $inv_item ? floatval($inv_item['landed_cost']) : 0;
        $total_landed    = ($order_status === 'Completed') ? ($sold_qty * $unit_landed) : 0;
        $net_profit      = ($order_status === 'Completed') ? ($total_sale - $total_landed - $shipping_fee) : 0;
        $notes           = sanitize_text_field(isset($raw['notes']) ? $raw['notes'] : '');
        $contact         = sanitize_text_field(isset($raw['customer_contact']) ? $raw['customer_contact'] : '');

        return array(
            'order_id'          => $order_id,
            'customer_name'     => $customer_name,
            'customer_city'     => $customer_city,
            'sku'               => $sku,
            'short_spec'        => $short_spec,
            'sold_qty'          => $sold_qty,
            'unit_sold_price'   => $unit_sold_price,
            'shipping_fee'      => $shipping_fee,
            'total_sale'        => $total_sale,
            'amount_received'   => $amount_received,
            'remaining_amount'  => $remaining,
            'sale_date'         => $sale_date,
            'followup_date'     => $followup_date,
            'payment_status'    => $payment_status,
            'followup_priority' => $priority,
            'order_status'      => $order_status,
            'sold_by'           => $sold_by,
            'sales_channel'     => $sales_channel,
            'payment_method'    => $payment_method,
            'landed_cost'       => $total_landed,
            'net_profit'        => $net_profit,
            'notes'             => $notes,
            'customer_contact'  => $contact,
        );
    }

    public function format_stock_status($live_qty, $prod_status = 'Active') {
        if ($prod_status !== 'Active' || $live_qty <= 0) {
            return 'Out of Stock (0)';
        }
        if ($live_qty <= 2) {
            return 'Low Stock (' . intval($live_qty) . ')';
        }
        return 'In Stock (' . intval($live_qty) . ')';
    }

    public function recalculate_all_live_stock() {
        $inventory = get_option(self::OPT_INVENTORY, array());
        $sales     = get_option(self::OPT_SALES, array());

        $completed_sold_by_sku = array();
        foreach ($sales as $s) {
            if (isset($s['order_status']) && $s['order_status'] === 'Completed' && !empty($s['sku'])) {
                $sku = $s['sku'];
                if (!isset($completed_sold_by_sku[$sku])) {
                    $completed_sold_by_sku[$sku] = 0;
                }
                $completed_sold_by_sku[$sku] += intval($s['sold_qty']);
            }
        }

        foreach ($inventory as $sku => &$item) {
            if ($item['product_status'] !== 'Active') {
                $item['live_qty'] = 0;
            } else {
                $sold = isset($completed_sold_by_sku[$sku]) ? $completed_sold_by_sku[$sku] : 0;
                $item['live_qty'] = max(0, intval($item['purchased_qty']) - $sold);
            }
            $item['stock_status_label'] = $this->format_stock_status($item['live_qty'], $item['product_status']);
        }
        unset($item);

        update_option(self::OPT_INVENTORY, $inventory);
    }

    public function sync_single_item_to_woocommerce($sku) {
        if (!function_exists('wc_get_product_id_by_sku') && !post_type_exists('product')) {
            return false;
        }
        $inventory = get_option(self::OPT_INVENTORY, array());
        if (!isset($inventory[$sku])) {
            return false;
        }
        $item = $inventory[$sku];

        $existing_id = 0;
        if (function_exists('wc_get_product_id_by_sku')) {
            $existing_id = wc_get_product_id_by_sku($sku);
        }
        if (!$existing_id) {
            $posts = get_posts(array(
                'post_type'   => 'product',
                'meta_key'    => '_sku',
                'meta_value'  => $sku,
                'post_status' => 'any',
                'numberposts' => 1,
                'fields'      => 'ids',
            ));
            if (!empty($posts)) {
                $existing_id = $posts[0];
            }
        }

        $seo_title = sprintf(
            '%s %s | %s %sth Gen | %sGB RAM | %s SSD | %s %s',
            $item['brand'],
            $item['model_name'],
            $item['processor'],
            $item['generation'],
            $item['ram'],
            $item['storage'],
            $item['display_size'],
            $item['touch_type']
        );

        $short_desc = sprintf(
            '<strong>%s</strong> — Excellent Condition Grade A+ (100%% Tested). Includes Genuine Windows 11 Pro + Lifetime MS Office + Foxit PDF Editor + 10 Days Check Warranty + 6 Months Service Warranty + Free COD Delivery Across Saudi Arabia.',
            esc_html($item['short_spec'])
        );

        $full_html = '<h3>English Specifications &amp; Value Bundle</h3>';
        $full_html .= '<pre style="white-space:pre-wrap;font-family:inherit;">' . esc_html($item['long_spec']) . '</pre>';
        if (!empty($item['arabic_spec'])) {
            $full_html .= '<hr/><div dir="rtl" lang="ar"><h3>المواصفات بالعربية (Saudi Arabia Delivery &amp; Warranty)</h3>';
            $full_html .= '<pre style="white-space:pre-wrap;font-family:inherit;">' . esc_html($item['arabic_spec']) . '</pre></div>';
        }

        $post_data = array(
            'post_title'   => $seo_title,
            'post_content' => $full_html,
            'post_excerpt' => $short_desc,
            'post_status'  => ($item['product_status'] === 'Active') ? 'publish' : 'draft',
            'post_type'    => 'product',
        );

        remove_action('save_post_product', array($this, 'on_woocommerce_product_saved'), 20);
        if ($existing_id) {
            $post_data['ID'] = $existing_id;
            $product_id = wp_update_post($post_data);
        } else {
            $product_id = wp_insert_post($post_data);
        }
        add_action('save_post_product', array($this, 'on_woocommerce_product_saved'), 20, 3);

        if (!$product_id || is_wp_error($product_id)) {
            return false;
        }

        $final_sale_price = max(0, floatval($item['retail_price']) - floatval($item['discount']));
        $reg_price = floatval($item['regular_price']) > 0 ? floatval($item['regular_price']) : floatval($item['retail_price']);
        $live_qty = intval($item['live_qty']);
        $in_stock_status = ($live_qty > 0 && $item['product_status'] === 'Active') ? 'instock' : 'outofstock';

        update_post_meta($product_id, '_sku', $sku);
        update_post_meta($product_id, '_regular_price', $reg_price);
        update_post_meta($product_id, '_sale_price', $final_sale_price);
        update_post_meta($product_id, '_price', $final_sale_price);
        update_post_meta($product_id, '_manage_stock', 'yes');
        update_post_meta($product_id, '_stock', $live_qty);
        update_post_meta($product_id, '_stock_status', $in_stock_status);
        update_post_meta($product_id, '_visibility', 'visible');
        update_post_meta($product_id, '_slt_arabic_spec', $item['arabic_spec']);
        update_post_meta($product_id, '_slt_photos_url', $item['photos_url']);
        update_post_meta($product_id, '_slt_video_url', $item['video_url']);
        update_post_meta($product_id, '_slt_zip_url', $item['zip_url']);

        if (taxonomy_exists('product_cat')) {
            wp_set_object_terms($product_id, array('SAUDI ARABIA', $item['brand'], $item['touch_type']), 'product_cat', false);
        }
        if (taxonomy_exists('product_tag')) {
            wp_set_object_terms($product_id, array($item['brand'], 'Laptop KSA', 'Duba', 'Tabuk', 'Yanbu', 'Riyadh', 'Jeddah', 'Grade A+ Laptop'), 'product_tag', false);
        }

        return $product_id;
    }

    public function sync_all_to_woocommerce() {
        $inventory = get_option(self::OPT_INVENTORY, array());
        $synced = 0;
        foreach ($inventory as $sku => $item) {
            if ($this->sync_single_item_to_woocommerce($sku)) {
                $synced++;
            }
        }
        return $synced;
    }

    public function push_to_google_sheets($payload) {
        $settings = get_option(self::OPT_SETTINGS, array());
        if (empty($settings['apps_script_url'])) {
            return false;
        }
        $payload['token'] = isset($settings['secret_token']) ? $settings['secret_token'] : '';
        $response = wp_remote_post($settings['apps_script_url'], array(
            'timeout' => 20,
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode($payload),
        ));
        return !is_wp_error($response);
    }

    // =========================================================================
    // WOOCOMMERCE HOOKS (2-WAY SYNC WHEN ORDERS OR PRODUCTS CHANGE IN WP)
    // =========================================================================
    public function on_woocommerce_order_completed($order_id) {
        if (!function_exists('wc_get_order')) {
            return;
        }
        if (get_post_meta($order_id, '_slt_ims_synced', true)) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $sales = get_option(self::OPT_SALES, array());
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (empty($customer_name)) {
            $customer_name = 'Website Customer #' . $order_id;
        }
        $city = $order->get_billing_city() ?: 'KSA';
        $phone = $order->get_billing_phone() ?: '';
        $payment_method = $order->get_payment_method_title() ?: 'COD';

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            $sku = $product->get_sku();
            if (empty($sku)) continue;

            $qty = max(1, intval($item->get_quantity()));
            $line_total = floatval($item->get_total());
            $unit_price = $line_total / $qty;

            $sale_entry = $this->sanitize_sale_item(array(
                'order_id'         => 'WC-' . $order_id,
                'customer_name'    => $customer_name,
                'customer_city'    => $city,
                'sku'              => $sku,
                'sold_qty'         => $qty,
                'unit_sold_price'  => $unit_price,
                'shipping_fee'     => floatval($order->get_shipping_total()),
                'amount_received'  => $line_total,
                'sale_date'        => current_time('Y-m-d'),
                'order_status'     => 'Completed',
                'sold_by'          => 'Website',
                'sales_channel'    => 'Website',
                'payment_method'   => $payment_method,
                'notes'            => 'Auto-synced from WooCommerce Order #' . $order_id,
                'customer_contact' => $phone,
            ));

            $sales[] = $sale_entry;
            $this->push_to_google_sheets(array(
                'action' => 'upsert_sale',
                'sale'   => $sale_entry,
            ));
        }

        update_option(self::OPT_SALES, $sales);
        update_post_meta($order_id, '_slt_ims_synced', '1');
        $this->recalculate_all_live_stock();
    }

    public function on_woocommerce_product_saved($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        $sku = get_post_meta($post_id, '_sku', true);
        if (empty($sku)) {
            return;
        }
        $sku = strtoupper(trim($sku));
        $inventory = get_option(self::OPT_INVENTORY, array());
        if (!isset($inventory[$sku])) {
            return;
        }
        $reg_price  = floatval(get_post_meta($post_id, '_regular_price', true));
        $sale_price = floatval(get_post_meta($post_id, '_sale_price', true));
        $stock      = get_post_meta($post_id, '_stock', true);

        if ($reg_price > 0) {
            $inventory[$sku]['regular_price'] = $reg_price;
        }
        if ($sale_price > 0) {
            $inventory[$sku]['final_price'] = $sale_price;
            $inventory[$sku]['retail_price'] = $sale_price + floatval($inventory[$sku]['discount']);
        }
        if ($stock !== '' && $stock !== false) {
            $inventory[$sku]['live_qty'] = intval($stock);
            $inventory[$sku]['stock_status_label'] = $this->format_stock_status(intval($stock), $inventory[$sku]['product_status']);
        }
        update_option(self::OPT_INVENTORY, $inventory);
        $this->push_to_google_sheets(array(
            'action' => 'upsert_purchase',
            'item'   => $inventory[$sku],
        ));
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================
    public function ajax_save_purchase() {
        check_ajax_referer('slt_ims_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $item = $this->sanitize_inventory_item($_POST);
        if (empty($item['sku'])) {
            wp_send_json_error(array('message' => 'SKU is required (e.g. SLT-009).'));
        }
        $inventory = get_option(self::OPT_INVENTORY, array());
        $inventory[$item['sku']] = $item;
        update_option(self::OPT_INVENTORY, $inventory);
        $this->recalculate_all_live_stock();

        $inventory = get_option(self::OPT_INVENTORY, array());
        $saved_item = $inventory[$item['sku']];

        $wc_id = $this->sync_single_item_to_woocommerce($item['sku']);
        $sheet_synced = $this->push_to_google_sheets(array(
            'action' => 'upsert_purchase',
            'item'   => $saved_item,
        ));

        wp_send_json_success(array(
            'message'      => "SKU {$item['sku']} saved in WordPress, synced to WooCommerce" . ($sheet_synced ? ' & Google Sheets!' : '!'),
            'item'         => $saved_item,
            'wc_product_id'=> $wc_id,
        ));
    }

    public function ajax_delete_sku() {
        check_ajax_referer('slt_ims_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $sku = strtoupper(sanitize_text_field(isset($_POST['sku']) ? $_POST['sku'] : ''));
        $inventory = get_option(self::OPT_INVENTORY, array());
        if (isset($inventory[$sku])) {
            unset($inventory[$sku]);
            update_option(self::OPT_INVENTORY, $inventory);
            wp_send_json_success(array('message' => "SKU {$sku} removed."));
        }
        wp_send_json_error(array('message' => 'SKU not found.'));
    }

    public function ajax_save_sale() {
        check_ajax_referer('slt_ims_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $sale = $this->sanitize_sale_item($_POST);
        if (empty($sale['sku'])) {
            wp_send_json_error(array('message' => 'Please select a Laptop SKU.'));
        }
        $sales = get_option(self::OPT_SALES, array());
        $updated = false;
        foreach ($sales as $idx => $s) {
            if ($s['order_id'] === $sale['order_id']) {
                $sales[$idx] = $sale;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            array_unshift($sales, $sale);
        }
        update_option(self::OPT_SALES, $sales);
        $this->recalculate_all_live_stock();
        $this->sync_single_item_to_woocommerce($sale['sku']);
        $sheet_synced = $this->push_to_google_sheets(array(
            'action' => 'upsert_sale',
            'sale'   => $sale,
        ));

        wp_send_json_success(array(
            'message' => "Sale {$sale['order_id']} recorded, stock updated" . ($sheet_synced ? ' & synced to Google Sheets!' : '!'),
            'sale'    => $sale,
        ));
    }

    public function ajax_update_sale_status() {
        check_ajax_referer('slt_ims_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $order_id = sanitize_text_field(isset($_POST['order_id']) ? $_POST['order_id'] : '');
        $sales = get_option(self::OPT_SALES, array());
        foreach ($sales as $idx => &$s) {
            if ($s['order_id'] === $order_id) {
                $s['order_status']      = 'Completed';
                $s['amount_received']   = $s['total_sale'];
                $s['remaining_amount']  = 0;
                $s['payment_status']    = 'Paid';
                $s['followup_priority'] = '🟢 Paid / Completed';
                $updated_sale = $this->sanitize_sale_item($s);
                $sales[$idx] = $updated_sale;
                update_option(self::OPT_SALES, $sales);
                $this->recalculate_all_live_stock();
                $this->sync_single_item_to_woocommerce($updated_sale['sku']);
                $this->push_to_google_sheets(array(
                    'action' => 'upsert_sale',
                    'sale'   => $updated_sale,
                ));
                wp_send_json_success(array('message' => "Order {$order_id} marked Completed & Paid!"));
            }
        }
        wp_send_json_error(array('message' => 'Order not found.'));
    }

    public function ajax_upload_csv() {
        check_ajax_referer('slt_ims_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        if (empty($_FILES['csv_file']['tmp_name'])) {
            wp_send_json_error(array('message' => 'No CSV file uploaded.'));
        }
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            wp_send_json_error(array('message' => 'Could not read uploaded CSV file.'));
        }
        $imported = $this->parse_and_import_csv_stream($handle);
        fclose($handle);
        $this->recalculate_all_live_stock();
        $wc_count = $this->sync_all_to_woocommerce();

        wp_send_json_success(array(
            'message' => "Successfully imported {$imported} laptops from Sheet CSV and synced {$wc_count} WooCommerce products!",
        ));
    }

    public function parse_and_import_csv_stream($handle) {
        $headers = fgetcsv($handle);
        if (!$headers) return 0;
        $norm_headers = array_map(function($h) {
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $h), '_'));
        }, $headers);

        $inventory = get_option(self::OPT_INVENTORY, array());
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row) || count($row) < 3) continue;
            $assoc = array();
            foreach ($norm_headers as $i => $key) {
                $assoc[$key] = isset($row[$i]) ? $row[$i] : '';
            }
            $sku = '';
            if (!empty($assoc['sku'])) $sku = trim($assoc['sku']);
            if (empty($sku) || strpos(strtoupper($sku), 'SLT-') !== 0) continue;

            $existing = isset($inventory[$sku]) ? $inventory[$sku] : array();

            $stock_str = isset($assoc['stock_status']) ? $assoc['stock_status'] : (isset($assoc['stock']) ? $assoc['stock'] : '');
            $live_qty = isset($existing['live_qty']) ? $existing['live_qty'] : 1;
            if (preg_match('/(\d+)/', $stock_str, $m)) {
                $live_qty = intval($m[1]);
            }

            $merged = array_merge($existing, array(
                'sku'           => $sku,
                'brand'         => !empty($assoc['brand']) ? $assoc['brand'] : (isset($existing['brand']) ? $existing['brand'] : 'Microsoft'),
                'model_name'    => !empty($assoc['model_name']) ? $assoc['model_name'] : (isset($existing['model_name']) ? $existing['model_name'] : 'Surface Laptop'),
                'touch_type'    => !empty($assoc['touch_non_touch']) ? $assoc['touch_non_touch'] : (isset($existing['touch_type']) ? $existing['touch_type'] : 'Touch'),
                'processor'     => !empty($assoc['processor']) ? $assoc['processor'] : (isset($existing['processor']) ? $existing['processor'] : 'i7'),
                'generation'    => !empty($assoc['generation']) ? $assoc['generation'] : (isset($existing['generation']) ? $existing['generation'] : '10'),
                'ram'           => !empty($assoc['ram']) ? $assoc['ram'] : (isset($existing['ram']) ? $existing['ram'] : '16'),
                'storage'       => !empty($assoc['storage']) ? $assoc['storage'] : (isset($existing['storage']) ? $existing['storage'] : '256GB'),
                'display_size'  => !empty($assoc['display_size']) ? $assoc['display_size'] : (isset($existing['display_size']) ? $existing['display_size'] : '13.5"'),
                'graphics'      => !empty($assoc['graphics']) ? $assoc['graphics'] : (isset($existing['graphics']) ? $existing['graphics'] : 'Intel Iris Plus'),
                'short_spec'    => !empty($assoc['short_specification_auto']) ? $assoc['short_specification_auto'] : (!empty($assoc['short_specification']) ? $assoc['short_specification'] : (isset($existing['short_spec']) ? $existing['short_spec'] : '')),
                'purchased_qty' => !empty($assoc['quantity']) ? intval($assoc['quantity']) : (isset($existing['purchased_qty']) ? $existing['purchased_qty'] : $live_qty),
                'live_qty'      => $live_qty,
                'unit_cost'     => !empty($assoc['unit_purchase_cost']) ? $assoc['unit_purchase_cost'] : (isset($existing['unit_cost']) ? $existing['unit_cost'] : 0),
                'shipping_cost' => !empty($assoc['shipping_cost']) ? $assoc['shipping_cost'] : (isset($existing['shipping_cost']) ? $existing['shipping_cost'] : 200),
                'retail_price'  => !empty($assoc['retail_price']) ? $assoc['retail_price'] : (!empty($assoc['sale_price']) ? $assoc['sale_price'] : (isset($existing['retail_price']) ? $existing['retail_price'] : 0)),
                'discount'      => isset($assoc['discount']) && $assoc['discount'] !== '' ? $assoc['discount'] : (isset($existing['discount']) ? $existing['discount'] : 0),
                'regular_price' => !empty($assoc['regular_price']) ? $assoc['regular_price'] : (isset($existing['regular_price']) ? $existing['regular_price'] : 0),
                'long_spec'     => !empty($assoc['complete_specification']) ? $assoc['complete_specification'] : (!empty($assoc['long_specification']) ? $assoc['long_specification'] : (isset($existing['long_spec']) ? $existing['long_spec'] : '')),
                'arabic_spec'   => !empty($assoc['haraj_arabic_specification']) ? $assoc['haraj_arabic_specification'] : (isset($existing['arabic_spec']) ? $existing['arabic_spec'] : ''),
            ));

            $inventory[$sku] = $this->sanitize_inventory_item($merged);
            $count++;
        }

        update_option(self::OPT_INVENTORY, $inventory);
        return $count;
    }

    public function ajax_pull_google_sheet() {
        check_ajax_referer('slt_ims_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $settings = get_option(self::OPT_SETTINGS, array());

        // Try Apps Script Web App first if configured
        if (!empty($settings['apps_script_url'])) {
            $url = add_query_arg('token', urlencode($settings['secret_token']), $settings['apps_script_url']);
            $resp = wp_remote_get($url, array('timeout' => 20));
            if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                $data = json_decode(wp_remote_retrieve_body($resp), true);
                if (is_array($data) && !empty($data['purchases'])) {
                    $inventory = get_option(self::OPT_INVENTORY, array());
                    $c = 0;
                    foreach ($data['purchases'] as $raw_item) {
                        if (empty($raw_item['sku'])) continue;
                        $item = $this->sanitize_inventory_item($raw_item);
                        $inventory[$item['sku']] = $item;
                        $c++;
                    }
                    update_option(self::OPT_INVENTORY, $inventory);
                    if (!empty($data['sales']) && is_array($data['sales'])) {
                        $sales_list = array();
                        foreach ($data['sales'] as $rs) {
                            if (empty($rs['order_id'])) continue;
                            $sales_list[] = $this->sanitize_sale_item($rs);
                        }
                        update_option(self::OPT_SALES, $sales_list);
                    }
                    $this->recalculate_all_live_stock();
                    $this->sync_all_to_woocommerce();
                    wp_send_json_success(array('message' => "Pulled {$c} SKUs & live sales directly via Google Apps Script 2-Way Bridge!"));
                }
            }
        }

        // Fallback: Direct Google Sheets CSV Export URL using Sheet ID
        $sheet_id = !empty($settings['team_sheet_id']) ? $settings['team_sheet_id'] : self::DEFAULT_TEAM_SHEET_ID;
        $csv_url = "https://docs.google.com/spreadsheets/d/{$sheet_id}/export?format=csv";
        $resp = wp_remote_get($csv_url, array('timeout' => 20));
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            wp_send_json_error(array('message' => 'Could not reach Google Sheet directly. Make sure the Sheet sharing is set to "Anyone with link" or paste the Google Apps Script Web App URL in Settings.'));
        }
        $body = wp_remote_retrieve_body($resp);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $body);
        rewind($stream);
        $imported = $this->parse_and_import_csv_stream($stream);
        fclose($stream);
        $this->recalculate_all_live_stock();
        $this->sync_all_to_woocommerce();

        wp_send_json_success(array('message' => "Pulled {$imported} laptops from Google Sheet ({$sheet_id}) and updated WooCommerce!"));
    }

    public function ajax_push_all_to_sheet() {
        check_ajax_referer('slt_ims_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $settings = get_option(self::OPT_SETTINGS, array());
        if (empty($settings['apps_script_url'])) {
            wp_send_json_error(array('message' => 'Please enter your Google Apps Script Web App URL in Tab 6 first.'));
        }
        $ok = $this->push_to_google_sheets(array(
            'action'    => 'full_push_from_wp',
            'purchases' => array_values(get_option(self::OPT_INVENTORY, array())),
            'sales'     => get_option(self::OPT_SALES, array()),
            'queue'     => get_option(self::OPT_QUEUE, array()),
        ));
        if ($ok) {
            wp_send_json_success(array('message' => 'All WordPress Inventory, Sales, and Post Queue rows pushed to Google Sheets!'));
        }
        wp_send_json_error(array('message' => 'Failed to reach Google Apps Script URL.'));
    }

    public function ajax_sync_woocommerce() {
        check_ajax_referer('slt_ims_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $count = $this->sync_all_to_woocommerce();
        wp_send_json_success(array('message' => "Synced {$count} laptops to WooCommerce Products with bilingual English + Arabic descriptions!"));
    }

    public function ajax_save_settings() {
        check_ajax_referer('slt_ims_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $settings = get_option(self::OPT_SETTINGS, array());
        $settings['master_sheet_id']  = sanitize_text_field(isset($_POST['master_sheet_id']) ? $_POST['master_sheet_id'] : self::DEFAULT_MASTER_SHEET_ID);
        $settings['team_sheet_id']    = sanitize_text_field(isset($_POST['team_sheet_id']) ? $_POST['team_sheet_id'] : self::DEFAULT_TEAM_SHEET_ID);
        $settings['queue_sheet_id']   = sanitize_text_field(isset($_POST['queue_sheet_id']) ? $_POST['queue_sheet_id'] : self::DEFAULT_QUEUE_SHEET_ID);
        $settings['apps_script_url']  = esc_url_raw(isset($_POST['apps_script_url']) ? $_POST['apps_script_url'] : '');
        $settings['make_webhook_url'] = esc_url_raw(isset($_POST['make_webhook_url']) ? $_POST['make_webhook_url'] : '');
        $settings['secret_token']     = sanitize_text_field(isset($_POST['secret_token']) ? $_POST['secret_token'] : $settings['secret_token']);
        $settings['whatsapp_number']  = sanitize_text_field(isset($_POST['whatsapp_number']) ? $_POST['whatsapp_number'] : '966553760854');
        update_option(self::OPT_SETTINGS, $settings);
        wp_send_json_success(array('message' => 'Settings saved successfully!'));
    }

    public function ajax_trigger_post() {
        check_ajax_referer('slt_ims_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $mode          = sanitize_text_field(isset($_POST['mode']) ? $_POST['mode'] : 'now');
        $sku           = strtoupper(sanitize_text_field(isset($_POST['sku']) ? $_POST['sku'] : 'SLT-001'));
        $schedule_time = sanitize_text_field(isset($_POST['schedule_time']) ? $_POST['schedule_time'] : current_time('Y-m-d H:i') . ' KSA');
        $video_url     = esc_url_raw(isset($_POST['video_url']) ? $_POST['video_url'] : '');
        $title         = sanitize_text_field(isset($_POST['title']) ? $_POST['title'] : '');
        $short_caption = sanitize_textarea_field(isset($_POST['short_caption']) ? $_POST['short_caption'] : '');
        $full_desc     = sanitize_textarea_field(isset($_POST['full_desc']) ? $_POST['full_desc'] : '');
        $arabic_spec   = sanitize_textarea_field(isset($_POST['arabic_spec']) ? $_POST['arabic_spec'] : '');
        $platforms     = sanitize_text_field(isset($_POST['platforms']) ? $_POST['platforms'] : 'YouTube, TikTok, Facebook, Instagram, Haraj KSA');

        $queue = get_option(self::OPT_QUEUE, array());
        $row_id = 'POST-' . str_pad(strval(count($queue) + 1), 3, '0', STR_PAD_LEFT);

        $post_item = array(
            'row_id'        => $row_id,
            'schedule_time' => $schedule_time,
            'sku'           => $sku,
            'video_url'     => $video_url,
            'title'         => $title,
            'short_caption' => $short_caption,
            'full_desc'     => $full_desc,
            'arabic_spec'   => $arabic_spec,
            'platforms'     => $platforms,
            'status'        => ($mode === 'now') ? 'Posted (Webhook Sent)' : 'Ready',
        );

        $settings = get_option(self::OPT_SETTINGS, array());
        $webhook_fired = false;

        if ($mode === 'now' && !empty($settings['make_webhook_url'])) {
            $resp = wp_remote_post($settings['make_webhook_url'], array(
                'timeout' => 15,
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => wp_json_encode($post_item),
            ));
            $webhook_fired = !is_wp_error($resp);
        }

        array_unshift($queue, $post_item);
        update_option(self::OPT_QUEUE, $queue);

        // Also sync to SLT Auto-Post Queue sheet via Google Apps Script
        $this->push_to_google_sheets(array(
            'action'    => 'append_post_queue',
            'post_item' => $post_item,
        ));

        $msg = ($mode === 'now')
            ? ($webhook_fired ? "Post {$row_id} ({$sku}) sent immediately to Make.com Webhook & logged in Queue!" : "Post {$row_id} ({$sku}) logged & pushed to Google Sheet Queue! (Add Make.com Webhook URL in Settings for instant push).")
            : "Post {$row_id} ({$sku}) scheduled for {$schedule_time} in Auto-Post Queue & Google Sheets!";

        wp_send_json_success(array('message' => $msg, 'post_item' => $post_item));
    }

    public function process_scheduled_posts() {
        $settings = get_option(self::OPT_SETTINGS, array());
        if (empty($settings['make_webhook_url'])) {
            return;
        }
        $queue = get_option(self::OPT_QUEUE, array());
        $now_ts = current_time('timestamp');
        $changed = false;

        foreach ($queue as &$item) {
            if ($item['status'] !== 'Ready') continue;
            $sched_clean = trim(str_ireplace('KSA', '', $item['schedule_time']));
            $sched_ts = strtotime($sched_clean);
            if ($sched_ts && $sched_ts <= $now_ts) {
                $resp = wp_remote_post($settings['make_webhook_url'], array(
                    'timeout' => 15,
                    'headers' => array('Content-Type' => 'application/json'),
                    'body'    => wp_json_encode($item),
                ));
                if (!is_wp_error($resp)) {
                    $item['status'] = 'Posted (Auto-Scheduled)';
                    $changed = true;
                }
            }
        }
        unset($item);
        if ($changed) {
            update_option(self::OPT_QUEUE, $queue);
        }
    }

    // =========================================================================
    // UI RENDERERS (ADMIN & FRONTEND SHORTCODES)
    // =========================================================================
    public function render_admin_page() {
        echo $this->render_hub_interface(true);
    }

    public function render_team_shortcode($atts) {
        return $this->render_hub_interface(false);
    }

    public function render_full_portal_shortcode($atts) {
        $is_admin = current_user_can('manage_options');
        return $this->render_hub_interface($is_admin);
    }

    public function render_hub_interface($is_admin = true) {
        $this->recalculate_all_live_stock();
        $inventory = get_option(self::OPT_INVENTORY, array());
        $sales     = get_option(self::OPT_SALES, array());
        $queue     = get_option(self::OPT_QUEUE, array());
        $settings  = get_option(self::OPT_SETTINGS, array());
        $nonce     = wp_create_nonce('slt_ims_nonce');
        $ajax_url  = admin_url('admin-ajax.php');
        $rest_url  = rest_url('slt-ims/v1/sync');

        // Calculate KPIs
        $total_purchased = 0;
        $live_stock      = 0;
        $low_stock_count = 0;
        $total_cost_sar  = 0;
        foreach ($inventory as $item) {
            $total_purchased += intval($item['purchased_qty']);
            if ($item['product_status'] === 'Active') {
                $live_stock += intval($item['live_qty']);
                if (intval($item['live_qty']) > 0 && intval($item['live_qty']) <= 2) {
                    $low_stock_count++;
                }
            }
            $total_cost_sar += floatval($item['total_bill']);
        }

        $completed_units_sold = 0;
        $total_revenue_sar    = 0;
        $cash_received_sar    = 0;
        $receivables_sar      = 0;
        $net_profit_sar       = 0;
        foreach ($sales as $s) {
            if ($s['order_status'] === 'Completed') {
                $completed_units_sold += intval($s['sold_qty']);
            }
            $total_revenue_sar += floatval($s['total_sale']);
            $cash_received_sar += floatval($s['amount_received']);
            $receivables_sar   += floatval($s['remaining_amount']);
            $net_profit_sar    += floatval($s['net_profit']);
        }

        ob_start();
        ?>
        <style>
            .slt-ims-wrap {
                font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, 'Noto Naskh Arabic', sans-serif;
                background: #f8fafc;
                color: #0f172a;
                padding: 20px;
                border-radius: 14px;
                max-width: 1440px;
                margin: 15px auto;
                box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
                border: 1px solid #e2e8f0;
            }
            .slt-ims-header {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                align-items: center;
                background: linear-gradient(135deg, #0f172a 0%, #0d9488 100%);
                color: #ffffff;
                padding: 20px 26px;
                border-radius: 12px;
                margin-bottom: 20px;
                gap: 14px;
            }
            .slt-ims-header h1 {
                color: #ffffff;
                margin: 0 0 6px 0;
                font-size: 23px;
                font-weight: 700;
            }
            .slt-ims-header p {
                margin: 0;
                color: #ccfbf1;
                font-size: 13.5px;
            }
            .slt-ims-badges {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .slt-badge {
                background: rgba(255,255,255,0.14);
                color: #fff;
                padding: 7px 13px;
                border-radius: 8px;
                font-size: 12.5px;
                font-weight: 600;
                text-decoration: none;
                border: 1px solid rgba(255,255,255,0.22);
                transition: all 0.18s;
            }
            .slt-badge:hover {
                background: #f59e0b;
                color: #0f172a;
                border-color: #f59e0b;
            }
            .slt-tabs {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
                margin-bottom: 20px;
                border-bottom: 2px solid #e2e8f0;
                padding-bottom: 10px;
            }
            .slt-tab-btn {
                background: #ffffff;
                color: #334155;
                border: 1px solid #cbd5e1;
                padding: 10px 16px;
                border-radius: 8px;
                font-size: 13.5px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.18s;
            }
            .slt-tab-btn.active, .slt-tab-btn:hover {
                background: #0d9488;
                color: #ffffff;
                border-color: #0d9488;
            }
            .slt-tab-panel {
                display: none;
            }
            .slt-tab-panel.active {
                display: block;
            }
            .slt-kpi-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 14px;
                margin-bottom: 22px;
            }
            .slt-kpi-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-left: 5px solid #0d9488;
                border-radius: 10px;
                padding: 16px;
                box-shadow: 0 2px 6px rgba(0,0,0,0.03);
            }
            .slt-kpi-card.warn { border-left-color: #f59e0b; }
            .slt-kpi-card.gold { border-left-color: #2563eb; }
            .slt-kpi-card.profit { border-left-color: #16a34a; }
            .slt-kpi-title {
                font-size: 12.5px;
                color: #64748b;
                font-weight: 600;
                text-transform: uppercase;
            }
            .slt-kpi-val {
                font-size: 24px;
                font-weight: 800;
                color: #0f172a;
                margin-top: 6px;
            }
            .slt-filter-bar {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-bottom: 18px;
                background: #ffffff;
                padding: 14px;
                border-radius: 10px;
                border: 1px solid #e2e8f0;
                align-items: center;
            }
            .slt-input, .slt-select, .slt-textarea {
                padding: 9px 12px;
                border: 1px solid #cbd5e1;
                border-radius: 7px;
                font-size: 13.5px;
                background: #fff;
                color: #0f172a;
                width: 100%;
                box-sizing: border-box;
            }
            .slt-cards-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(410px, 1fr));
                gap: 18px;
            }
            .slt-sku-card {
                background: #ffffff;
                border: 1px solid #cbd5e1;
                border-radius: 12px;
                padding: 18px;
                box-shadow: 0 3px 10px rgba(0,0,0,0.04);
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            .slt-sku-top {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            .slt-sku-tag {
                background: #0f172a;
                color: #f8fafc;
                font-weight: 700;
                padding: 4px 10px;
                border-radius: 6px;
                font-size: 12.5px;
            }
            .slt-stock-pill {
                padding: 4px 10px;
                border-radius: 99px;
                font-size: 12px;
                font-weight: 700;
            }
            .slt-stock-pill.in { background: #dcfce7; color: #166534; }
            .slt-stock-pill.low { background: #fef3c7; color: #92400e; }
            .slt-stock-pill.out { background: #fee2e2; color: #991b1b; }
            .slt-price-strip {
                display: flex;
                justify-content: space-between;
                background: #f1f5f9;
                padding: 10px 12px;
                border-radius: 8px;
                margin: 10px 0 14px 0;
                font-size: 13px;
            }
            .slt-copy-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                margin-bottom: 12px;
            }
            .slt-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding: 8px 12px;
                border-radius: 7px;
                font-size: 12.5px;
                font-weight: 600;
                cursor: pointer;
                border: 1px solid #cbd5e1;
                background: #f8fafc;
                color: #0f172a;
                text-decoration: none;
                transition: all 0.15s;
            }
            .slt-btn:hover {
                background: #0d9488;
                color: #ffffff;
                border-color: #0d9488;
            }
            .slt-btn.primary {
                background: #0d9488;
                color: #ffffff;
                border-color: #0d9488;
            }
            .slt-btn.primary:hover {
                background: #0f766e;
            }
            .slt-btn.arabic {
                background: #eff6ff;
                color: #1e40af;
                border-color: #bfdbfe;
            }
            .slt-btn.arabic:hover {
                background: #2563eb;
                color: #ffffff;
            }
            .slt-btn.amber {
                background: #fffbeb;
                color: #92400e;
                border-color: #fde68a;
            }
            .slt-btn.amber:hover {
                background: #f59e0b;
                color: #0f172a;
            }
            .slt-form-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
                gap: 14px;
                margin-bottom: 16px;
            }
            .slt-form-box {
                background: #ffffff;
                border: 1px solid #cbd5e1;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 22px;
            }
            .slt-form-box h3 {
                margin-top: 0;
                color: #0f172a;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 10px;
            }
            .slt-label {
                display: block;
                font-size: 12.5px;
                font-weight: 600;
                color: #334155;
                margin-bottom: 5px;
            }
            .slt-table-wrap {
                overflow-x: auto;
                background: #ffffff;
                border-radius: 10px;
                border: 1px solid #cbd5e1;
            }
            .slt-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 13px;
            }
            .slt-table th {
                background: #0f172a;
                color: #ffffff;
                text-align: left;
                padding: 10px 12px;
                white-space: nowrap;
            }
            .slt-table td {
                padding: 9px 12px;
                border-bottom: 1px solid #e2e8f0;
                vertical-align: middle;
            }
            .slt-toast {
                position: fixed;
                bottom: 24px;
                right: 24px;
                background: #0f172a;
                color: #ffffff;
                padding: 12px 20px;
                border-radius: 9px;
                font-size: 13.5px;
                font-weight: 600;
                z-index: 99999;
                display: none;
                box-shadow: 0 8px 25px rgba(0,0,0,0.22);
                border-left: 5px solid #10b981;
            }
            @media (max-width: 640px) {
                .slt-cards-grid { grid-template-columns: 1fr; }
                .slt-copy-grid { grid-template-columns: 1fr; }
            }
        </style>

        <div class="slt-ims-wrap" id="slt-ims-app">
            <div class="slt-ims-header">
                <div>
                    <h1>💻 SLT SMART IMS — Inventory, Sales & Team Marketing Hub (v2.1 Open Source)</h1>
                    <p>2-Way Google Sheets Sync • WooCommerce Live Stock • 1-Click Haraj / Arabic & Social Media Copy • Make.com Automation Queue</p>
                </div>
                <div class="slt-ims-badges">
                    <a class="slt-badge" href="https://docs.google.com/spreadsheets/d/<?php echo esc_attr($settings['master_sheet_id']); ?>/edit" target="_blank" rel="noopener">📊 Open SLT IMS KSA Sheet</a>
                    <a class="slt-badge" href="https://docs.google.com/spreadsheets/d/<?php echo esc_attr($settings['team_sheet_id']); ?>/edit" target="_blank" rel="noopener">📋 Open Team Live Stock</a>
                    <a class="slt-badge" href="https://drive.google.com/drive/folders/<?php echo esc_attr($settings['drive_folder_id']); ?>" target="_blank" rel="noopener">📁 Open Team Drive Workspace</a>
                </div>
            </div>

            <!-- NAVIGATION TABS -->
            <div class="slt-tabs">
                <button type="button" class="slt-tab-btn active" onclick="sltSwitchTab('tab-marketing', this)">🚀 1. Team Marketing & 1-Click Copy Hub</button>
                <button type="button" class="slt-tab-btn" onclick="sltSwitchTab('tab-dashboard', this)">📊 2. Live Stock & Dashboard</button>
                <?php if ($is_admin): ?>
                <button type="button" class="slt-tab-btn" onclick="sltSwitchTab('tab-purchase', this)">💻 3. New Laptop Purchase Entry</button>
                <button type="button" class="slt-tab-btn" onclick="sltSwitchTab('tab-sales', this)">💰 4. Quick Sale Entry & CRM</button>
                <?php endif; ?>
                <button type="button" class="slt-tab-btn" onclick="sltSwitchTab('tab-automation', this)">🤖 5. AI Auto-Post & Make.com Queue</button>
                <?php if ($is_admin): ?>
                <button type="button" class="slt-tab-btn" onclick="sltSwitchTab('tab-sync', this)">🔄 6. Google Sheets 2-Way Sync & Upload</button>
                <?php endif; ?>
            </div>

            <!-- =================================================================
                 TAB 1: TEAM MARKETING & 1-CLICK SOCIAL / HARAJ COPY HUB
                 ================================================================= -->
            <div id="tab-marketing" class="slt-tab-panel active">
                <div class="slt-filter-bar">
                    <div style="flex: 2; min-width: 220px;">
                        <input type="text" id="slt-search-input" class="slt-input" placeholder="🔍 Search by SKU (SLT-001), Model (Surface Laptop 3, Tecra), Processor (i7, Ryzen)..." oninput="sltFilterMarketingCards()">
                    </div>
                    <div style="flex: 1; min-width: 160px;">
                        <select id="slt-brand-filter" class="slt-select" onchange="sltFilterMarketingCards()">
                            <option value="ALL">All Brands (Microsoft, Toshiba, Dell, HP...)</option>
                            <option value="Microsoft">Microsoft Surface</option>
                            <option value="Toshiba">Toshiba / Dynabook</option>
                            <option value="Dell">Dell</option>
                            <option value="HP">HP</option>
                            <option value="Lenovo">Lenovo</option>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 160px;">
                        <select id="slt-stock-filter" class="slt-select" onchange="sltFilterMarketingCards()">
                            <option value="ALL">All Stock Statuses</option>
                            <option value="AVAILABLE">In Stock / Low Stock Only</option>
                            <option value="LOW">Low Stock Alert Only</option>
                        </select>
                    </div>
                </div>

                <div class="slt-cards-grid" id="slt-marketing-grid">
                    <?php foreach ($inventory as $sku => $item):
                        $live_q = intval($item['live_qty']);
                        $is_active = ($item['product_status'] === 'Active' && $live_q > 0);
                        $pill_class = !$is_active ? 'out' : ($live_q <= 2 ? 'low' : 'in');
                        $final_offer = max(0, floatval($item['retail_price']) - floatval($item['discount']));

                        $seo_title = sprintf(
                            '%s %s | %s %sth Gen | %sGB RAM | %s SSD | %s %s — Only %s SAR in KSA',
                            $item['brand'], $item['model_name'], $item['processor'], $item['generation'],
                            $item['ram'], $item['storage'], $item['display_size'], $item['touch_type'],
                            number_format($final_offer)
                        );
                        $haraj_title = sprintf(
                            'لابتوب %s %s (%s الجيل %s | رام %sGB | تخزين %s | شاشة %s لمس) نظيف جداً A+ بسعر %s ريال',
                            $item['brand'], $item['model_name'], $item['processor'], $item['generation'],
                            $item['ram'], $item['storage'], $item['display_size'], number_format($final_offer)
                        );
                        $price_box = sprintf(
                            "💻 Model: %s %s (%s)\n💰 Regular Market Price: %s SAR\n🏷️ SLT Retail Price: %s SAR\n🔥 Special Discount: -%s SAR\n✅ Final Offer Price: %s SAR Only!\n🚚 Free Delivery & Cash on Delivery (COD) Across Saudi Arabia\n🛡️ 10 Days Check + 6 Months Service Warranty\n📲 Order on WhatsApp: https://wa.me/966553760854 | Catalog: https://wa.me/c/966553760854",
                            $item['brand'], $item['model_name'], $sku,
                            number_format($item['regular_price']),
                            number_format($item['retail_price']),
                            number_format($item['discount']),
                            number_format($final_offer)
                        );
                        $tiktok_caption = sprintf(
                            "Hot Deal in KSA! 🔥 %s %s (%s %sth Gen, %sGB RAM, %s SSD, %s %s) in Excellent Grade A+ condition for ONLY %s SAR (Instead of %s SAR)! Includes Genuine Windows 11 Pro + Lifetime MS Office + 10 Days Check Warranty + 6 Months Service Warranty + Free KSA Delivery & COD. Order via WhatsApp: https://wa.me/966553760854 | Website: https://smartlifeteck.com/ #SmartLifeTeck #LaptopKSA #HarajKSA #RiyadhLaptops #JeddahLaptops #Duba #Yanbu #%s",
                            $item['brand'], $item['model_name'], $item['processor'], $item['generation'],
                            $item['ram'], $item['storage'], $item['display_size'], $item['touch_type'],
                            number_format($final_offer), number_format($item['regular_price']),
                            preg_replace('/[^A-Za-z0-9]/', '', $item['model_name'])
                        );
                        $wa_pitch = sprintf(
                            "السلام عليكم ورحمة الله وبركاته 🌟\nWelcome to Smart Life Teck (سمارت لايف تك)!\n\n💻 *%s %s (SKU: %s)*\n✔️ Specs: %s\n✔️ Condition: Excellent Grade A+ (100%% Tested)\n✔️ Free Package: Genuine Windows 11 Pro + Lifetime MS Office + PDF Editor + SLT Smart App\n✔️ Warranty: 10 Days Check Replacement + 6 Months Service Warranty\n\n💰 *Special Offer Price: %s SAR* (After %s SAR Discount | Regular: %s SAR)\n🚚 *Free Delivery & Cash on Delivery (COD) Across All KSA*\n\n📦 View Full Catalog: https://wa.me/c/966553760854\n🌐 Website: https://smartlifeteck.com/",
                            $item['brand'], $item['model_name'], $sku, $item['short_spec'],
                            number_format($final_offer), number_format($item['discount']), number_format($item['regular_price'])
                        );
                        $voiceover_script = sprintf(
                            "Stop scrolling! Looking for a clean Grade A+ business laptop in Saudi Arabia without paying %s Riyals? Check out this %s %s (SKU %s) at Smart Life Teck! Powered by %s %sth Generation, %sGB RAM, fast %s SSD, and a stunning %s %s display. Today you get it for ONLY %s Riyals! Includes lifetime activated Windows 11 Pro, Microsoft Office, SLT Smart App, 10 Days Check Warranty, 6 Months Service Warranty, and Free Cash on Delivery across Saudi Arabia. Message us on WhatsApp at +966 55 376 0854 or visit smartlifeteck.com right now!",
                            number_format($item['regular_price']), $item['brand'], $item['model_name'], $sku,
                            $item['processor'], $item['generation'], $item['ram'], $item['storage'],
                            $item['display_size'], $item['touch_type'], number_format($final_offer)
                        );
                        $hashtags = "#SmartLifeTeck #LaptopKSA #UsedLaptopKSA #HarajKSA #MicrosoftSurfaceKSA #RiyadhLaptops #JeddahLaptops #Dammam #Yanbu #Tabuk #Duba #Madinah #BusinessLaptop #حراج_السعودية #لابتوب_مستعمل";
                    ?>
                    <div class="slt-sku-card"
                         data-sku="<?php echo esc_attr(strtolower($sku)); ?>"
                         data-brand="<?php echo esc_attr($item['brand']); ?>"
                         data-search="<?php echo esc_attr(strtolower($sku . ' ' . $item['brand'] . ' ' . $item['model_name'] . ' ' . $item['short_spec'])); ?>"
                         data-avail="<?php echo $is_active ? '1' : '0'; ?>"
                         data-low="<?php echo ($is_active && $live_q <= 2) ? '1' : '0'; ?>">
                        <div>
                            <div class="slt-sku-top">
                                <span class="slt-sku-tag"><?php echo esc_html($sku); ?> • <?php echo esc_html($item['brand']); ?></span>
                                <span class="slt-stock-pill <?php echo esc_attr($pill_class); ?>"><?php echo esc_html($item['stock_status_label']); ?></span>
                            </div>
                            <h3 style="margin: 6px 0; font-size: 16px; color: #0f172a;"><?php echo esc_html($item['short_spec']); ?></h3>

                            <div class="slt-price-strip">
                                <div><span style="color:#64748b;">Regular:</span> <del><?php echo number_format($item['regular_price']); ?> SAR</del></div>
                                <div><span style="color:#64748b;">Retail:</span> <?php echo number_format($item['retail_price']); ?> SAR</div>
                                <div><span style="color:#dc2626;font-weight:700;">Disc: -<?php echo number_format($item['discount']); ?></span></div>
                                <div style="color:#0d9488;font-weight:800;font-size:14.5px;">Offer: <?php echo number_format($final_offer); ?> SAR</div>
                            </div>

                            <!-- 1-CLICK MARKETING COPY BUTTONS -->
                            <div class="slt-copy-grid">
                                <button type="button" class="slt-btn" data-copy="<?php echo esc_attr($seo_title); ?>" onclick="sltCopyText(this, 'SEO English Title')">📋 Copy Title (EN)</button>
                                <button type="button" class="slt-btn arabic" data-copy="<?php echo esc_attr($haraj_title); ?>" onclick="sltCopyText(this, 'Haraj Arabic Title')">🇸🇦 Copy Haraj Title (AR)</button>
                                <button type="button" class="slt-btn" data-copy="<?php echo esc_attr($item['short_spec']); ?>" onclick="sltCopyText(this, 'Short Specification')">⚡ Copy Short Spec</button>
                                <button type="button" class="slt-btn" data-copy="<?php echo esc_attr($item['long_spec']); ?>" onclick="sltCopyText(this, 'Complete English Specification')">📄 Copy Long Spec (EN)</button>
                                <button type="button" class="slt-btn arabic" data-copy="<?php echo esc_attr($item['arabic_spec']); ?>" onclick="sltCopyText(this, 'Haraj / Arabic Full Spec')">🇸🇦 Copy Haraj Spec (AR)</button>
                                <button type="button" class="slt-btn amber" data-copy="<?php echo esc_attr($price_box); ?>" onclick="sltCopyText(this, 'Price & Discount Offer Box')">💰 Copy Price & Offer</button>
                                <button type="button" class="slt-btn" data-copy="<?php echo esc_attr($tiktok_caption); ?>" onclick="sltCopyText(this, 'TikTok / Reels / Shorts Caption')">🎬 Copy TikTok/Reels</button>
                                <button type="button" class="slt-btn" data-copy="<?php echo esc_attr($wa_pitch); ?>" onclick="sltCopyText(this, 'WhatsApp Customer Reply (EN+AR)')">💬 Copy WhatsApp Reply</button>
                                <button type="button" class="slt-btn" data-copy="<?php echo esc_attr($voiceover_script); ?>" onclick="sltCopyText(this, '30s AI Video Voiceover Script')">🎙️ Copy Video Script</button>
                                <button type="button" class="slt-btn" data-copy="<?php echo esc_attr($hashtags); ?>" onclick="sltCopyText(this, '15 KSA Hashtags')">#️⃣ Copy KSA Hashtags</button>
                            </div>
                        </div>

                        <!-- MEDIA & AUTOMATION ACTION BAR -->
                        <div style="display:flex; gap:7px; flex-wrap:wrap; border-top:1px solid #e2e8f0; padding-top:10px;">
                            <?php if (!empty($item['photos_url'])): ?>
                                <a class="slt-btn" href="<?php echo esc_url($item['photos_url']); ?>" target="_blank" rel="noopener">🖼️ Drive Photos</a>
                            <?php endif; ?>
                            <?php if (!empty($item['zip_url'])): ?>
                                <a class="slt-btn" href="<?php echo esc_url($item['zip_url']); ?>" target="_blank" rel="noopener">📥 Download ZIP</a>
                            <?php endif; ?>
                            <?php if (!empty($item['video_url'])): ?>
                                <a class="slt-btn" href="<?php echo esc_url($item['video_url']); ?>" target="_blank" rel="noopener">▶️ Video</a>
                            <?php endif; ?>
                            <button type="button" class="slt-btn primary" style="margin-left:auto;"
                                    data-sku="<?php echo esc_attr($sku); ?>"
                                    data-title="<?php echo esc_attr($seo_title); ?>"
                                    data-short="<?php echo esc_attr($tiktok_caption); ?>"
                                    data-full="<?php echo esc_attr($item['long_spec']); ?>"
                                    data-arabic="<?php echo esc_attr($item['arabic_spec']); ?>"
                                    data-video="<?php echo esc_attr($item['video_url']); ?>"
                                    onclick="sltPrefillPostQueue(this)">🚀 Send to Auto-Post / Make.com</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- =================================================================
                 TAB 2: LIVE STOCK & FINANCIAL DASHBOARD
                 ================================================================= -->
            <div id="tab-dashboard" class="slt-tab-panel">
                <div class="slt-kpi-grid">
                    <div class="slt-kpi-card">
                        <div class="slt-kpi-title">Total Units Purchased</div>
                        <div class="slt-kpi-val"><?php echo intval($total_purchased); ?> Units</div>
                    </div>
                    <div class="slt-kpi-card gold">
                        <div class="slt-kpi-title">Completed Units Sold</div>
                        <div class="slt-kpi-val"><?php echo intval($completed_units_sold); ?> Sold (<?php echo count($sales); ?> Orders)</div>
                    </div>
                    <div class="slt-kpi-card profit">
                        <div class="slt-kpi-title">Live Stock Available</div>
                        <div class="slt-kpi-val"><?php echo intval($live_stock); ?> Units</div>
                    </div>
                    <div class="slt-kpi-card warn">
                        <div class="slt-kpi-title">Low Stock Items (≤2)</div>
                        <div class="slt-kpi-val"><?php echo intval($low_stock_count); ?> Models</div>
                    </div>
                    <?php if ($is_admin): ?>
                    <div class="slt-kpi-card">
                        <div class="slt-kpi-title">Total Purchase Bill (Admin)</div>
                        <div class="slt-kpi-val"><?php echo number_format($total_cost_sar); ?> SAR</div>
                    </div>
                    <div class="slt-kpi-card gold">
                        <div class="slt-kpi-title">Total Sales Revenue</div>
                        <div class="slt-kpi-val"><?php echo number_format($total_revenue_sar); ?> SAR</div>
                    </div>
                    <div class="slt-kpi-card warn">
                        <div class="slt-kpi-title">Outstanding Receivables</div>
                        <div class="slt-kpi-val"><?php echo number_format($receivables_sar); ?> SAR</div>
                    </div>
                    <div class="slt-kpi-card profit">
                        <div class="slt-kpi-title">Realized Net Profit (Completed)</div>
                        <div class="slt-kpi-val"><?php echo number_format($net_profit_sar); ?> SAR</div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="slt-table-wrap">
                    <table class="slt-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Brand & Model</th>
                                <th>Short Specification</th>
                                <th>Purchased</th>
                                <th>Live Stock</th>
                                <th>Retail (SAR)</th>
                                <th>Discount</th>
                                <th>Final Offer</th>
                                <th>Regular Price</th>
                                <?php if ($is_admin): ?>
                                <th>Landed Cost</th>
                                <th>Expected Profit/Unit</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <?php if ($is_admin): ?>
                                <th>Quick Edit</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $sku => $item):
                                $final_p = max(0, floatval($item['retail_price']) - floatval($item['discount']));
                                $exp_profit = $final_p - floatval($item['landed_cost']);
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($sku); ?></strong></td>
                                <td><?php echo esc_html($item['brand'] . ' ' . $item['model_name']); ?></td>
                                <td><?php echo esc_html($item['short_spec']); ?></td>
                                <td><?php echo intval($item['purchased_qty']); ?></td>
                                <td><strong><?php echo esc_html($item['stock_status_label']); ?></strong></td>
                                <td><?php echo number_format($item['retail_price']); ?> SAR</td>
                                <td style="color:#dc2626;">-<?php echo number_format($item['discount']); ?> SAR</td>
                                <td style="color:#0d9488;font-weight:700;"><?php echo number_format($final_p); ?> SAR</td>
                                <td><del><?php echo number_format($item['regular_price']); ?> SAR</del></td>
                                <?php if ($is_admin): ?>
                                <td><?php echo number_format($item['landed_cost']); ?> SAR</td>
                                <td style="color:#16a34a;font-weight:700;">+<?php echo number_format($exp_profit); ?> SAR</td>
                                <?php endif; ?>
                                <td><?php echo esc_html($item['product_status']); ?></td>
                                <?php if ($is_admin): ?>
                                <td>
                                    <button type="button" class="slt-btn" onclick='sltLoadItemForEdit(<?php echo wp_json_encode($item); ?>)'>✏️ Edit</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($is_admin): ?>
            <!-- =================================================================
                 TAB 3: EASY NEW LAPTOP PURCHASE ENTRY (ADMIN)
                 ================================================================= -->
            <div id="tab-purchase" class="slt-tab-panel">
                <div class="slt-form-box">
                    <h3>💻 Easy New Laptop Purchase Entry / Edit Existing SKU (Auto-Syncs with Google Sheets & WooCommerce)</h3>
                    <form id="slt-purchase-form" onsubmit="sltSubmitPurchase(event)">
                        <div class="slt-form-grid">
                            <div>
                                <label class="slt-label">SKU Code (e.g. SLT-009)</label>
                                <input type="text" name="sku" id="p_sku" class="slt-input" placeholder="SLT-009" required>
                            </div>
                            <div>
                                <label class="slt-label">Brand</label>
                                <select name="brand" id="p_brand" class="slt-select">
                                    <option value="Microsoft">Microsoft</option>
                                    <option value="Toshiba">Toshiba</option>
                                    <option value="Dell">Dell</option>
                                    <option value="HP">HP</option>
                                    <option value="Lenovo">Lenovo</option>
                                    <option value="Apple">Apple</option>
                                    <option value="Acer">Acer</option>
                                </select>
                            </div>
                            <div>
                                <label class="slt-label">Model Name</label>
                                <input type="text" name="model_name" id="p_model" class="slt-input" placeholder="Surface Laptop 4" required>
                            </div>
                            <div>
                                <label class="slt-label">Touch / Non-Touch</label>
                                <select name="touch_type" id="p_touch" class="slt-select">
                                    <option value="Touch">Touch</option>
                                    <option value="Non-Touch">Non-Touch</option>
                                </select>
                            </div>
                            <div>
                                <label class="slt-label">Processor</label>
                                <select name="processor" id="p_proc" class="slt-select">
                                    <option value="i5">i5</option>
                                    <option value="i7">i7</option>
                                    <option value="i9">i9</option>
                                    <option value="AMD Ryzen 5">AMD Ryzen 5</option>
                                    <option value="AMD Ryzen 7">AMD Ryzen 7</option>
                                    <option value="i3">i3</option>
                                </select>
                            </div>
                            <div>
                                <label class="slt-label">Generation</label>
                                <select name="generation" id="p_gen" class="slt-select">
                                    <option value="10">10</option>
                                    <option value="11">11</option>
                                    <option value="12">12</option>
                                    <option value="13">13</option>
                                    <option value="14">14</option>
                                    <option value="8">8</option>
                                    <option value="7">7</option>
                                </select>
                            </div>
                            <div>
                                <label class="slt-label">RAM (GB)</label>
                                <select name="ram" id="p_ram" class="slt-select">
                                    <option value="16">16</option>
                                    <option value="8">8</option>
                                    <option value="32">32</option>
                                    <option value="64">64</option>
                                </select>
                            </div>
                            <div>
                                <label class="slt-label">SSD Storage</label>
                                <select name="storage" id="p_storage" class="slt-select">
                                    <option value="256GB">256GB</option>
                                    <option value="512GB">512GB</option>
                                    <option value="1TB">1TB</option>
                                    <option value="128GB">128GB</option>
                                </select>
                            </div>
                            <div>
                                <label class="slt-label">Display Size</label>
                                <select name="display_size" id="p_display" class="slt-select">
                                    <option value='13.5"'>13.5"</option>
                                    <option value='12.3"'>12.3"</option>
                                    <option value='14.0"'>14.0"</option>
                                    <option value='15.6"'>15.6"</option>
                                    <option value='13.3"'>13.3"</option>
                                </select>
                            </div>
                            <div>
                                <label class="slt-label">Graphics</label>
                                <input type="text" name="graphics" id="p_gpu" class="slt-input" value="Intel Iris Plus">
                            </div>
                            <div>
                                <label class="slt-label">Purchased Quantity</label>
                                <input type="number" name="purchased_qty" id="p_qty" class="slt-input" value="5" min="0" oninput="sltCalcPurchaseMath()">
                            </div>
                            <div>
                                <label class="slt-label">Unit Purchase Cost (SAR)</label>
                                <input type="number" name="unit_cost" id="p_cost" class="slt-input" value="850" step="any" oninput="sltCalcPurchaseMath()">
                            </div>
                            <div>
                                <label class="slt-label">Shipping / Landed Cost per Unit (SAR)</label>
                                <input type="number" name="shipping_cost" id="p_ship" class="slt-input" value="200" step="any" oninput="sltCalcPurchaseMath()">
                            </div>
                            <div>
                                <label class="slt-label">Retail Price (SAR)</label>
                                <input type="number" name="retail_price" id="p_retail" class="slt-input" value="1575" step="any" oninput="sltCalcPurchaseMath()">
                            </div>
                            <div>
                                <label class="slt-label">Customer Discount (SAR)</label>
                                <input type="number" name="discount" id="p_disc" class="slt-input" value="75" step="any" oninput="sltCalcPurchaseMath()">
                            </div>
                            <div>
                                <label class="slt-label">Regular Market Price (SAR)</label>
                                <input type="number" name="regular_price" id="p_reg" class="slt-input" value="3250" step="any">
                            </div>
                            <div>
                                <label class="slt-label">Supplier Name</label>
                                <input type="text" name="supplier_name" id="p_supp" class="slt-input" value="Authorized Supplier">
                            </div>
                            <div>
                                <label class="slt-label">Purchase Date</label>
                                <input type="date" name="purchase_date" id="p_date" class="slt-input" value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                            </div>
                            <div>
                                <label class="slt-label">Drive Photos Folder URL</label>
                                <input type="url" name="photos_url" id="p_photos" class="slt-input" placeholder="https://drive.google.com/drive/folders/...">
                            </div>
                            <div>
                                <label class="slt-label">Photos ZIP Download URL</label>
                                <input type="url" name="zip_url" id="p_zip" class="slt-input" placeholder="https://drive.google.com/file/d/...">
                            </div>
                            <div>
                                <label class="slt-label">Video URL (YouTube / Drive)</label>
                                <input type="url" name="video_url" id="p_video" class="slt-input" placeholder="https://www.youtube.com/watch?v=...">
                            </div>
                            <div>
                                <label class="slt-label">Product Status</label>
                                <select name="product_status" id="p_status" class="slt-select">
                                    <option value="Active">Active</option>
                                    <option value="Returned to Supplier">Returned to Supplier</option>
                                    <option value="Draft">Draft</option>
                                </select>
                            </div>
                        </div>

                        <div id="slt-purchase-math-preview" style="background:#f0fdf4; border:1px solid #bbf7d0; padding:12px 16px; border-radius:8px; margin-bottom:14px; font-weight:600; color:#166534;">
                            💡 Auto-Calculation: Landed Unit Cost: 1,050 SAR | Total Purchase Bill: 5,250 SAR | Final Customer Offer Price: 1,500 SAR | Expected Profit/Unit: +450 SAR
                        </div>

                        <div style="margin-bottom: 14px;">
                            <button type="button" class="slt-btn amber" onclick="sltAutoGenerateSpecs()">✨ 1-Click Auto-Generate English & Haraj Arabic Specifications from Dropdowns</button>
                        </div>

                        <div class="slt-form-grid" style="grid-template-columns: 1fr 1fr;">
                            <div>
                                <label class="slt-label">Complete English Specification (For Website, YouTube & Facebook)</label>
                                <textarea name="long_spec" id="p_long_spec" rows="8" class="slt-textarea"></textarea>
                            </div>
                            <div>
                                <label class="slt-label">Haraj / Arabic Specification (المواصفات بالعربية لحراج وواتساب)</label>
                                <textarea name="arabic_spec" id="p_arabic_spec" rows="8" class="slt-textarea" dir="rtl"></textarea>
                            </div>
                        </div>

                        <button type="submit" class="slt-btn primary" style="padding:12px 24px; font-size:14px;">💾 Save Laptop Purchase + Sync to WooCommerce & Google Sheets</button>
                    </form>
                </div>
            </div>

            <!-- =================================================================
                 TAB 4: QUICK SALE ENTRY & CUSTOMER RECEIVABLES CRM (ADMIN)
                 ================================================================= -->
            <div id="tab-sales" class="slt-tab-panel">
                <div class="slt-form-box">
                    <h3>💰 Quick Laptop Sale Entry (Auto-Deducts Live Stock & Syncs to 2_Sales_Entry in Google Sheets)</h3>
                    <form id="slt-sale-form" onsubmit="sltSubmitSale(event)">
                        <div class="slt-form-grid">
                            <div>
                                <label class="slt-label">Order ID</label>
                                <input type="text" name="order_id" id="s_order_id" class="slt-input" value="SLT-0<?php echo count($sales) + 1; ?>" required>
                            </div>
                            <div>
                                <label class="slt-label">Select Laptop SKU</label>
                                <select name="sku" id="s_sku" class="slt-select" onchange="sltOnSaleSkuChange()" required>
                                    <?php foreach ($inventory as $sku => $item):
                                        $offer = max(0, floatval($item['retail_price']) - floatval($item['discount']));
                                    ?>
                                    <option value="<?php echo esc_attr($sku); ?>" data-price="<?php echo esc_attr($offer); ?>" data-landed="<?php echo esc_attr($item['landed_cost']); ?>">
                                        <?php echo esc_html("{$sku} — {$item['brand']} {$item['model_name']} ({$item['stock_status_label']}) — Offer: {$offer} SAR"); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="slt-label">Customer Name</label>
                                <input type="text" name="customer_name" class="slt-input" placeholder="e.g. Customer Name" required>
                            </div>
                            <div>
                                <label class="slt-label">Customer City (KSA)</label>
                                <input type="text" name="customer_city" class="slt-input" placeholder="Duba / Yanbu / Riyadh / Jeddah / Tabuk" value="Riyadh">
                            </div>
                            <div>
                                <label class="slt-label">Customer WhatsApp / Contact</label>
                                <input type="text" name="customer_contact" class="slt-input" placeholder="966 5XXXXXXXX">
                            </div>
                            <div>
                                <label class="slt-label">Sold Quantity</label>
                                <input type="number" name="sold_qty" id="s_qty" class="slt-input" value="1" min="1">
                            </div>
                            <div>
                                <label class="slt-label">Unit Sold Price (SAR)</label>
                                <input type="number" name="unit_sold_price" id="s_price" class="slt-input" value="1500" step="any">
                            </div>
                            <div>
                                <label class="slt-label">Shipping Fee / Cost (SAR)</label>
                                <input type="number" name="shipping_fee" id="s_ship" class="slt-input" value="25" step="any">
                            </div>
                            <div>
                                <label class="slt-label">Amount Received Now (SAR)</label>
                                <input type="number" name="amount_received" id="s_rcvd" class="slt-input" value="1500" step="any">
                            </div>
                            <div>
                                <label class="slt-label">Order Status</label>
                                <select name="order_status" class="slt-select">
                                    <option value="Completed">Completed (Deducts Live Stock)</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Cancelled">Cancelled</option>
                                    <option value="Returned">Returned</option>
                                </select>
                            </div>
                            <div>
                                <label class="slt-label">Sold By (Team Member)</label>
                                <select name="sold_by" class="slt-select">
                                    <option value="Shahzad">Shahzad</option>
                                    <option value="Hammad">Hammad</option>
                                    <option value="Danish">Danish</option>
                                    <option value="Maria">Maria</option>
                                    <option value="Ibrahim">Ibrahim</option>
                                    <option value="Daniyal">Daniyal</option>
                                    <option value="Dawood">Dawood</option>
                                </select>
                            </div>
                            <div>
                                <label class="slt-label">Sales Channel</label>
                                <select name="sales_channel" class="slt-select">
                                    <option value="Haraj">Haraj KSA</option>
                                    <option value="WhatsApp">WhatsApp</option>
                                    <option value="Facebook Marketplace">Facebook Marketplace</option>
                                    <option value="Website">Website (smartlifeteck.com)</option>
                                    <option value="TikTok">TikTok</option>
                                    <option value="Instagram">Instagram</option>
                                    <option value="Social Relation">Social Relation</option>
                                </select>
                            </div>
                            <div>
                                <label class="slt-label">Payment Method</label>
                                <select name="payment_method" class="slt-select">
                                    <option value="COD">Cash on Delivery (COD)</option>
                                    <option value="Bank Transfer">Bank Transfer (Al Rajhi / Riyad / AlJazira)</option>
                                    <option value="Cash">Cash</option>
                                    <option value="STC Pay">STC Pay / urpay</option>
                                </select>
                            </div>
                            <div>
                                <label class="slt-label">Sale Date</label>
                                <input type="date" name="sale_date" class="slt-input" value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                            </div>
                        </div>
                        <button type="submit" class="slt-btn primary" style="padding:11px 22px; font-size:14px;">✅ Record Sale + Deduct Stock + Sync to Google Sheets</button>
                    </form>
                </div>

                <div class="slt-table-wrap">
                    <table class="slt-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Customer & City</th>
                                <th>Contact</th>
                                <th>SKU</th>
                                <th>Qty</th>
                                <th>Total Sale</th>
                                <th>Received</th>
                                <th>Remaining</th>
                                <th>Payment Status</th>
                                <th>Order Status</th>
                                <th>Sold By / Channel</th>
                                <th>Net Profit</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $s): ?>
                            <tr>
                                <td><strong><?php echo esc_html($s['order_id']); ?></strong></td>
                                <td><?php echo esc_html($s['sale_date']); ?></td>
                                <td><?php echo esc_html($s['customer_name'] . ' (' . $s['customer_city'] . ')'); ?></td>
                                <td><?php echo esc_html($s['customer_contact']); ?></td>
                                <td><strong><?php echo esc_html($s['sku']); ?></strong></td>
                                <td><?php echo intval($s['sold_qty']); ?></td>
                                <td><?php echo number_format($s['total_sale']); ?> SAR</td>
                                <td style="color:#16a34a;"><?php echo number_format($s['amount_received']); ?> SAR</td>
                                <td style="color:<?php echo floatval($s['remaining_amount']) > 0 ? '#dc2626' : '#64748b'; ?>;font-weight:700;">
                                    <?php echo number_format($s['remaining_amount']); ?> SAR
                                </td>
                                <td><?php echo esc_html($s['followup_priority']); ?></td>
                                <td><?php echo esc_html($s['order_status']); ?></td>
                                <td><?php echo esc_html($s['sold_by'] . ' • ' . $s['sales_channel']); ?></td>
                                <td style="color:#0d9488;font-weight:700;"><?php echo number_format($s['net_profit']); ?> SAR</td>
                                <td>
                                    <?php if ($s['order_status'] !== 'Completed' || floatval($s['remaining_amount']) > 0): ?>
                                        <button type="button" class="slt-btn primary" onclick="sltMarkSaleCompleted('<?php echo esc_js($s['order_id']); ?>')">✔️ Mark Paid & Done</button>
                                    <?php else: ?>
                                        <span style="color:#16a34a;font-weight:600;">✓ Settled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- =================================================================
                 TAB 5: AI AUTOMATION, SINGLE POST & MAKE.COM SCHEDULE QUEUE
                 ================================================================= -->
            <div id="tab-automation" class="slt-tab-panel">
                <div class="slt-form-box">
                    <h3>🤖 1-Click Single Post OR Schedule to Make.com & SLT Auto-Post Queue Sheet</h3>
                    <form id="slt-post-form">
                        <div class="slt-form-grid">
                            <div>
                                <label class="slt-label">Select Laptop SKU</label>
                                <input type="text" id="q_sku" class="slt-input" value="SLT-001">
                            </div>
                            <div>
                                <label class="slt-label">Schedule Time (KSA Time — for Queue)</label>
                                <input type="text" id="q_time" class="slt-input" value="<?php echo esc_attr(current_time('Y-m-d') . ' 20:30 KSA'); ?>">
                            </div>
                            <div>
                                <label class="slt-label">Target Platforms</label>
                                <input type="text" id="q_platforms" class="slt-input" value="YouTube, TikTok, Facebook, Instagram, Haraj KSA, Google Business">
                            </div>
                            <div>
                                <label class="slt-label">Video / Media Drive URL</label>
                                <input type="url" id="q_video" class="slt-input" placeholder="https://drive.google.com/file/d/...">
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="slt-label">Post / YouTube / Haraj Title</label>
                            <input type="text" id="q_title" class="slt-input" placeholder="Microsoft Surface Laptop 3 Core i7 10th Gen 16GB RAM 256GB SSD | Only 1,500 SAR in KSA">
                        </div>
                        <div class="slt-form-grid" style="grid-template-columns: 1fr 1fr;">
                            <div>
                                <label class="slt-label">Short Caption (TikTok / Reels / Shorts / X)</label>
                                <textarea id="q_short" rows="4" class="slt-textarea"></textarea>
                            </div>
                            <div>
                                <label class="slt-label">Full Description (YouTube / Facebook / Website / Haraj)</label>
                                <textarea id="q_full" rows="4" class="slt-textarea"></textarea>
                            </div>
                        </div>
                        <div style="display:flex; gap:12px; flex-wrap:wrap;">
                            <button type="button" class="slt-btn primary" style="padding:11px 20px;" onclick="sltSubmitPost('now')">🚀 Post Single Item Now (Trigger Make.com Webhook)</button>
                            <button type="button" class="slt-btn amber" style="padding:11px 20px;" onclick="sltSubmitPost('schedule')">📅 Add to Schedule Queue (Syncs with SLT Auto-Post Queue Sheet)</button>
                        </div>
                    </form>
                </div>

                <div class="slt-table-wrap">
                    <table class="slt-table">
                        <thead>
                            <tr>
                                <th>Row ID</th>
                                <th>Schedule Time</th>
                                <th>SKU</th>
                                <th>Title</th>
                                <th>Platforms</th>
                                <th>Media Link</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($queue as $q): ?>
                            <tr>
                                <td><strong><?php echo esc_html($q['row_id']); ?></strong></td>
                                <td><?php echo esc_html($q['schedule_time']); ?></td>
                                <td><strong><?php echo esc_html($q['sku']); ?></strong></td>
                                <td><?php echo esc_html($q['title']); ?></td>
                                <td><?php echo esc_html($q['platforms']); ?></td>
                                <td>
                                    <?php if (!empty($q['video_url'])): ?>
                                        <a href="<?php echo esc_url($q['video_url']); ?>" target="_blank" rel="noopener">🎬 View Media</a>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo esc_html($q['status']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($is_admin): ?>
            <!-- =================================================================
                 TAB 6: 2-WAY GOOGLE SHEETS SYNC, CSV UPLOAD & SETTINGS (ADMIN)
                 ================================================================= -->
            <div id="tab-sync" class="slt-tab-panel">
                <div class="slt-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));">
                    <!-- BOX A: 1-CLICK SHEET / CSV UPLOAD & LIVE PULL -->
                    <div class="slt-form-box">
                        <h3>📥 1-Click Google Sheet Upload & Direct Live Sync</h3>
                        <p style="font-size:13px; color:#475569;">Whenever you upload a downloaded Google Sheet CSV or click Pull below, all laptops, prices, stock counts, and Arabic/English specs are imported into WordPress & WooCommerce automatically.</p>

                        <form id="slt-csv-upload-form" onsubmit="sltUploadCsv(event)" style="margin-bottom:18px; padding-bottom:16px; border-bottom:1px solid #e2e8f0;">
                            <label class="slt-label">Upload Google Sheet CSV File (1_Purchase_Entry / Team Live Stock / WooCommerce CSV)</label>
                            <input type="file" name="csv_file" id="slt_csv_file" accept=".csv" class="slt-input" style="margin-bottom:10px;" required>
                            <button type="submit" class="slt-btn primary">📤 Upload Sheet & Import All Data to WordPress</button>
                        </form>

                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <button type="button" class="slt-btn primary" onclick="sltPullFromGoogleSheet()">🔄 1-Click Pull All Live Data from Google Sheet Now</button>
                            <button type="button" class="slt-btn amber" onclick="sltPushAllToGoogleSheet()">☁️ 1-Click Push All WordPress Data to Google Sheets</button>
                            <button type="button" class="slt-btn" onclick="sltSyncAllWooCommerce()">🛒 1-Click Sync All Inventory to WooCommerce Store Products</button>
                        </div>
                    </div>

                    <!-- BOX B: 2-WAY SYNC & MAKE.COM SETTINGS -->
                    <div class="slt-form-box">
                        <h3>⚙️ 2-Way Google Sheets Bridge & Make.com Webhook Settings</h3>
                        <form id="slt-settings-form" onsubmit="sltSaveSettings(event)">
                            <div style="margin-bottom:10px;">
                                <label class="slt-label">Your WordPress Incoming Webhook URL (Paste in Google Apps Script)</label>
                                <input type="text" class="slt-input" value="<?php echo esc_attr($rest_url); ?>" readonly onclick="this.select()">
                            </div>
                            <div style="margin-bottom:10px;">
                                <label class="slt-label">Secret Sync Token (Shared between WP & Google Sheets)</label>
                                <input type="text" name="secret_token" class="slt-input" value="<?php echo esc_attr($settings['secret_token']); ?>">
                            </div>
                            <div style="margin-bottom:10px;">
                                <label class="slt-label">Google Apps Script Web App URL (For WP → Google Sheets Instant Push)</label>
                                <input type="url" name="apps_script_url" class="slt-input" value="<?php echo esc_attr($settings['apps_script_url']); ?>" placeholder="https://script.google.com/macros/s/.../exec">
                            </div>
                            <div style="margin-bottom:10px;">
                                <label class="slt-label">Make.com Webhook URL (For 1-Click Social Posting & Automation)</label>
                                <input type="url" name="make_webhook_url" class="slt-input" value="<?php echo esc_attr($settings['make_webhook_url']); ?>" placeholder="https://hook.eu1.make.com/...">
                            </div>
                            <div class="slt-form-grid" style="grid-template-columns:1fr 1fr;">
                                <div>
                                    <label class="slt-label">Master Sheet ID (SLT IMS KSA)</label>
                                    <input type="text" name="master_sheet_id" class="slt-input" value="<?php echo esc_attr($settings['master_sheet_id']); ?>">
                                </div>
                                <div>
                                    <label class="slt-label">Team Live Stock Sheet ID</label>
                                    <input type="text" name="team_sheet_id" class="slt-input" value="<?php echo esc_attr($settings['team_sheet_id']); ?>">
                                </div>
                            </div>
                            <button type="submit" class="slt-btn primary" style="margin-top:8px;">💾 Save Sync & Automation Settings</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div id="slt-toast" class="slt-toast">Copied!</div>

        <script>
        var sltAjaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        var sltNonce   = <?php echo wp_json_encode($nonce); ?>;

        function sltShowToast(msg) {
            var t = document.getElementById('slt-toast');
            if (!t) return;
            t.textContent = '✅ ' + msg;
            t.style.display = 'block';
            clearTimeout(window._sltToastTimer);
            window._sltToastTimer = setTimeout(function() { t.style.display = 'none'; }, 2800);
        }

        function sltSwitchTab(tabId, btn) {
            var panels = document.querySelectorAll('.slt-tab-panel');
            for (var i = 0; i < panels.length; i++) panels[i].classList.remove('active');
            var btns = document.querySelectorAll('.slt-tab-btn');
            for (var j = 0; j < btns.length; j++) btns[j].classList.remove('active');
            var target = document.getElementById(tabId);
            if (target) target.classList.add('active');
            if (btn) btn.classList.add('active');
        }

        function sltCopyText(btn, label) {
            var txt = btn.getAttribute('data-copy') || '';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(txt).then(function() {
                    sltShowToast(label + ' copied to clipboard!');
                });
            } else {
                var ta = document.createElement('textarea');
                ta.value = txt;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                sltShowToast(label + ' copied to clipboard!');
            }
        }

        function sltFilterMarketingCards() {
            var q = (document.getElementById('slt-search-input').value || '').toLowerCase();
            var brand = document.getElementById('slt-brand-filter').value;
            var stock = document.getElementById('slt-stock-filter').value;
            var cards = document.querySelectorAll('#slt-marketing-grid .slt-sku-card');
            for (var i = 0; i < cards.length; i++) {
                var c = cards[i];
                var matchQ = !q || (c.getAttribute('data-search') || '').indexOf(q) !== -1;
                var matchB = (brand === 'ALL') || (c.getAttribute('data-brand') === brand);
                var matchS = (stock === 'ALL') ||
                             (stock === 'AVAILABLE' && c.getAttribute('data-avail') === '1') ||
                             (stock === 'LOW' && c.getAttribute('data-low') === '1');
                c.style.display = (matchQ && matchB && matchS) ? 'flex' : 'none';
            }
        }

        function sltCalcPurchaseMath() {
            var qty = parseFloat(document.getElementById('p_qty').value) || 0;
            var cost = parseFloat(document.getElementById('p_cost').value) || 0;
            var ship = parseFloat(document.getElementById('p_ship').value) || 0;
            var retail = parseFloat(document.getElementById('p_retail').value) || 0;
            var disc = parseFloat(document.getElementById('p_disc').value) || 0;
            var landed = cost + ship;
            var bill = qty * landed;
            var offer = Math.max(0, retail - disc);
            var profit = offer - landed;
            var box = document.getElementById('slt-purchase-math-preview');
            if (box) {
                box.textContent = '💡 Auto-Calculation: Landed Unit Cost: ' + landed.toLocaleString() + ' SAR | Total Purchase Bill: ' + bill.toLocaleString() + ' SAR | Final Customer Offer Price: ' + offer.toLocaleString() + ' SAR | Expected Profit/Unit: ' + (profit >= 0 ? '+' : '') + profit.toLocaleString() + ' SAR';
            }
        }

        function sltAutoGenerateSpecs() {
            var brand = document.getElementById('p_brand').value;
            var model = document.getElementById('p_model').value || 'Laptop';
            var touch = document.getElementById('p_touch').value;
            var proc  = document.getElementById('p_proc').value;
            var gen   = document.getElementById('p_gen').value;
            var ram   = document.getElementById('p_ram').value;
            var ssd   = document.getElementById('p_storage').value;
            var disp  = document.getElementById('p_display').value;
            var gpu   = document.getElementById('p_gpu').value;

            var en = brand + ' ' + model + ' (' + disp + ' ' + touch + ')\n\n' +
                'Quick Specifications\n' +
                '✓ Processor: ' + proc + ' (' + gen + 'th Generation)\n' +
                '✓ Memory (RAM): ' + ram + ' GB High-Speed RAM\n' +
                '✓ Storage: ' + ssd + ' NVMe SSD\n' +
                '✓ Display: ' + disp + ' ' + touch + ' Display\n' +
                '✓ Graphics: ' + gpu + '\n' +
                '✓ Condition: Excellent Grade A+ (100% Tested)\n\n' +
                '━━━━━━━━━━━━━━━━━━━━━━\n' +
                'Software & Warranty Included Free:\n' +
                '✓ Windows 11 Pro + Microsoft Office + Foxit PDF Editor + SLT Smart App — Genuine | Lifetime Activated\n' +
                '✓ 10 Days Checking Warranty + 6 Months Service Warranty + Free AnyDesk Support\n' +
                '✓ Free Delivery Across Saudi Arabia | Cash On Delivery Available\n\n' +
                '🌐 Website: www.smartlifeteck.com\n' +
                '📱 WhatsApp Catalog: https://wa.me/c/966553760854\n' +
                '📞 WhatsApp: +966553760854';

            var ar = '━━━━━━━━━━━━━━━━━━━━━\n' +
                'لابتوب ' + brand + ' ' + model + ' (' + disp + ' ' + touch + ')\n\n' +
                'مواصفات سريعة:\n' +
                '- المعالج: ' + proc + ' (الجيل ' + gen + ')\n' +
                '- الذاكرة العشوائية (RAM): ' + ram + ' جيجابايت عالي السرعة\n' +
                '- التخزين: ' + ssd + ' NVMe SSD فائق السرعة\n' +
                '- الشاشة: ' + disp + ' (' + touch + ')\n' +
                '- كرت الشاشة: ' + gpu + '\n' +
                '- الحالة: ممتاز جداً A+ (مستعمل بحالة الجديد ومفحوص 100%)\n\n' +
                'الهدايا والبرامج المثبتة مجاناً:\n' +
                '✓ ويندوز 11 برو أصلي + مايكروسوفت أوفيس أصلي + Foxit PDF Editor (مفعل مدى الحياة)\n' +
                '✓ ضمان فحص لمدة 10 أيام + ضمان خدمة وصيانة مجانية لمدة 6 أشهر\n' +
                '✓ توصيل مجاني لجميع مدن المملكة العربية السعودية والدفع عند الاستلام\n\n' +
                '🌐 الموقع الإلكتروني: www.smartlifeteck.com\n' +
                '📱 كتالوج الواتساب: https://wa.me/c/966553760854\n' +
                '📞 الواتساب: +966553760854\n' +
                '━━━━━━━━━━━━━━━━━━━━━━';

            document.getElementById('p_long_spec').value = en;
            document.getElementById('p_arabic_spec').value = ar;
            sltShowToast('English & Haraj Arabic specifications generated!');
        }

        function sltLoadItemForEdit(item) {
            sltSwitchTab('tab-purchase', document.querySelectorAll('.slt-tab-btn')[2]);
            document.getElementById('p_sku').value = item.sku || '';
            document.getElementById('p_brand').value = item.brand || 'Microsoft';
            document.getElementById('p_model').value = item.model_name || '';
            document.getElementById('p_touch').value = item.touch_type || 'Touch';
            document.getElementById('p_proc').value = item.processor || 'i7';
            document.getElementById('p_gen').value = item.generation || '10';
            document.getElementById('p_ram').value = item.ram || '16';
            document.getElementById('p_storage').value = item.storage || '256GB';
            document.getElementById('p_display').value = item.display_size || '13.5"';
            document.getElementById('p_gpu').value = item.graphics || '';
            document.getElementById('p_qty').value = item.purchased_qty || 1;
            document.getElementById('p_cost').value = item.unit_cost || 0;
            document.getElementById('p_ship').value = item.shipping_cost || 200;
            document.getElementById('p_retail').value = item.retail_price || 0;
            document.getElementById('p_disc').value = item.discount || 0;
            document.getElementById('p_reg').value = item.regular_price || 0;
            document.getElementById('p_supp').value = item.supplier_name || 'Authorized Supplier';
            document.getElementById('p_date').value = item.purchase_date || '';
            document.getElementById('p_photos').value = item.photos_url || '';
            document.getElementById('p_zip').value = item.zip_url || '';
            document.getElementById('p_video').value = item.video_url || '';
            document.getElementById('p_status').value = item.product_status || 'Active';
            document.getElementById('p_long_spec').value = item.long_spec || '';
            document.getElementById('p_arabic_spec').value = item.arabic_spec || '';
            sltCalcPurchaseMath();
            sltShowToast('Loaded ' + item.sku + ' into Purchase Editor!');
        }

        function sltOnSaleSkuChange() {
            var sel = document.getElementById('s_sku');
            if (!sel) return;
            var opt = sel.options[sel.selectedIndex];
            var price = opt ? opt.getAttribute('data-price') : '';
            if (price) {
                document.getElementById('s_price').value = price;
                document.getElementById('s_rcvd').value = price;
            }
        }

        function sltPrefillPostQueue(btn) {
            document.getElementById('q_sku').value = btn.getAttribute('data-sku') || '';
            document.getElementById('q_title').value = btn.getAttribute('data-title') || '';
            document.getElementById('q_short').value = btn.getAttribute('data-short') || '';
            document.getElementById('q_full').value = btn.getAttribute('data-full') || '';
            document.getElementById('q_video').value = btn.getAttribute('data-video') || '';
            var tabBtns = document.querySelectorAll('.slt-tab-btn');
            for (var i = 0; i < tabBtns.length; i++) {
                if (tabBtns[i].textContent.indexOf('AI Auto-Post') !== -1) {
                    sltSwitchTab('tab-automation', tabBtns[i]);
                    break;
                }
            }
            sltShowToast('Loaded ' + btn.getAttribute('data-sku') + ' into AI Post Queue!');
        }

        function sltSubmitPurchase(e) {
            e.preventDefault();
            var fd = new FormData(document.getElementById('slt-purchase-form'));
            fd.append('action', 'slt_ims_save_purchase');
            fd.append('nonce', sltNonce);
            fetch(sltAjaxUrl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        sltShowToast(res.data.message);
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        alert(res.data ? res.data.message : 'Error saving purchase.');
                    }
                });
        }

        function sltSubmitSale(e) {
            e.preventDefault();
            var fd = new FormData(document.getElementById('slt-sale-form'));
            fd.append('action', 'slt_ims_save_sale');
            fd.append('nonce', sltNonce);
            fetch(sltAjaxUrl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        sltShowToast(res.data.message);
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        alert(res.data ? res.data.message : 'Error recording sale.');
                    }
                });
        }

        function sltMarkSaleCompleted(orderId) {
            var fd = new FormData();
            fd.append('action', 'slt_ims_update_sale_status');
            fd.append('nonce', sltNonce);
            fd.append('order_id', orderId);
            fetch(sltAjaxUrl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        sltShowToast(res.data.message);
                        setTimeout(function() { location.reload(); }, 900);
                    }
                });
        }

        function sltSubmitPost(mode) {
            var fd = new FormData();
            fd.append('action', 'slt_ims_trigger_post');
            fd.append('nonce', sltNonce);
            fd.append('mode', mode);
            fd.append('sku', document.getElementById('q_sku').value);
            fd.append('schedule_time', document.getElementById('q_time').value);
            fd.append('platforms', document.getElementById('q_platforms').value);
            fd.append('video_url', document.getElementById('q_video').value);
            fd.append('title', document.getElementById('q_title').value);
            fd.append('short_caption', document.getElementById('q_short').value);
            fd.append('full_desc', document.getElementById('q_full').value);
            fetch(sltAjaxUrl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        sltShowToast(res.data.message);
                        setTimeout(function() { location.reload(); }, 1100);
                    } else {
                        alert(res.data ? res.data.message : 'Error submitting post.');
                    }
                });
        }

        function sltUploadCsv(e) {
            e.preventDefault();
            var fd = new FormData(document.getElementById('slt-csv-upload-form'));
            fd.append('action', 'slt_ims_upload_csv');
            fd.append('nonce', sltNonce);
            sltShowToast('Uploading & syncing CSV with WooCommerce...');
            fetch(sltAjaxUrl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        sltShowToast(res.data.message);
                        setTimeout(function() { location.reload(); }, 1200);
                    } else {
                        alert(res.data ? res.data.message : 'CSV Upload error.');
                    }
                });
        }

        function sltPullFromGoogleSheet() {
            var fd = new FormData();
            fd.append('action', 'slt_ims_pull_google_sheet');
            fd.append('nonce', sltNonce);
            sltShowToast('Pulling latest inventory from Google Sheets...');
            fetch(sltAjaxUrl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        sltShowToast(res.data.message);
                        setTimeout(function() { location.reload(); }, 1200);
                    } else {
                        alert(res.data ? res.data.message : 'Pull error.');
                    }
                });
        }

        function sltPushAllToGoogleSheet() {
            var fd = new FormData();
            fd.append('action', 'slt_ims_push_all_to_sheet');
            fd.append('nonce', sltNonce);
            sltShowToast('Pushing all WordPress data to Google Sheets...');
            fetch(sltAjaxUrl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        sltShowToast(res.data.message);
                    } else {
                        alert(res.data ? res.data.message : 'Push error.');
                    }
                });
        }

        function sltSyncAllWooCommerce() {
            var fd = new FormData();
            fd.append('action', 'slt_ims_sync_woocommerce');
            fd.append('nonce', sltNonce);
            sltShowToast('Syncing all SKUs with WooCommerce Products...');
            fetch(sltAjaxUrl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        sltShowToast(res.data.message);
                    }
                });
        }

        function sltSaveSettings(e) {
            e.preventDefault();
            var fd = new FormData(document.getElementById('slt-settings-form'));
            fd.append('action', 'slt_ims_save_settings');
            fd.append('nonce', sltNonce);
            fetch(sltAjaxUrl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) sltShowToast(res.data.message);
                });
        }
        </script>
        <?php
        return ob_get_clean();
    }
}

new SLT_Inventory_Marketing_Hub();
