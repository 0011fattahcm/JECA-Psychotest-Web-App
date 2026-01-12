<?php
date_default_timezone_set('Asia/Jakarta');
session_start();

require_once "./config/database.php";
require_once "./controllers/AppHelpers.php";
require_once "./controllers/AdminController.php";
require_once "./controllers/UserController.php";
require_once "./controllers/TPAController.php";
require_once "./controllers/TAMController.php";
require_once "./controllers/KraeplinController.php";
require_once "./controllers/AntiCheatController.php";

$page = $_REQUEST['page'] ?? 'user-login';



switch ($page) {

/* ======================
       USER PAGES
       ======================*/
    case 'user-login':
        userLogin();
        break;

    case 'user-login-process':
        userLoginProcess();
        break;

    case 'user-dashboard':
        userDashboard();
        break;

    case 'user-logout':
        userLogout();
        break;
        
case 'ajax-user-status':
    ajaxUserStatus();
    break;

    /* ======================
       ADMIN AUTH PAGES
       ======================*/
    case 'admin-login':
        adminLoginPage();
        break;

case 'admin-login-process':
    adminLoginProcess();
    break;

    case 'admin-logout':
        adminLogout();
        break;


    /* ======================
       ADMIN DASHBOARD
       ======================*/
    case 'admin-dashboard':
        adminDashboard();
        break;

     case 'admin-user-toggle-active':
        adminToggleUserActive();
        break;   

         case 'admin-user-grant-retake':
        adminGrantRetake();
        break;  

case 'admin-test-window-save':
    adminSaveTestWindow();
    break;

    case 'ajax-test-window-status':
    ajaxTestWindowStatus();
    break;

    /* ======================
       ADMIN USERS
       ======================*/
  case 'admin-users':                // Kelola User (list)
        adminUsers();
        break;

    case 'admin-users-export-excel':
    adminUsersExportExcel();
    break;    

    case 'admin-add-user':            // Form tambah user
        adminAddUserPage();
        break;

    case 'admin-user-add-process':    // Proses simpan user baru
        adminAddUserProcess();
        break;

    case 'admin-user-edit':           // Form edit user
        adminUserEditPage();
        break;

    case 'admin-user-update-process': // Proses update user
        adminUserUpdateProcess();
        break;
    // Hapus User
    case 'admin-user-delete':
        adminUserDeleteProcess();
        break;

    

 case 'ajax-anti-cheat-event':
    ajaxAntiCheatEvent();
    exit;

// optional alias
case 'ajax-anti-cheat':
    ajaxAntiCheatEvent();
    exit;
    /* ======================
       ADMIN TPA
       ======================*/
case 'admin-tpa-list':
    adminTPAList();
    break;

case 'admin-tpa-add':
    adminTPAAddPage();
    break;

case 'admin-tpa-add-process':
    adminTPAAddProcess();
    break;

case 'admin-tpa-edit':
    adminTPAEditPage();
    break;

case 'admin-tpa-edit-process':
    adminTPAEditProcess();
    break;

case 'admin-tpa-delete':
    adminTPADelete();
    break;

    // ---------------- Hasil TPA ----------------
case 'admin-tpa-results':
    adminTPAResultsPage();
    break;

case 'admin-results':
    adminResultsPage();
    break;

    case 'admin-tpa-result-detail':
    adminTpaResultDetailPage();
    break;


case 'admin-results-export':
    adminResultsExportExcel();
    break;

    /* ======================
       ADMIN TAM
       ======================*/
    case 'admin-tam-package':
        adminTAMPackagePage();
        break;

    case 'admin-tam-package-save':
        adminTAMPackageSaveProcess();
        break;

// Admin – Soal TAM
case 'admin-tam-list':
    adminTAMListPage();
    break;
case 'admin-tam-add':
    adminTAMAddPage();
    break;
case 'admin-tam-add-process':
    adminTAMAddProcess();
    break;
case 'admin-tam-edit':
    adminTAMEditPage();
    break;
case 'admin-tam-edit-process':
    adminTAMEditProcess();
    break;
case 'admin-tam-delete':
    adminTAMDelete();
    break;
case 'user-tam-start':
    UsertamStimulus();
    break;

case 'user-tam-test':
    UsertamTest();
    break;

case 'ajax-tam-progress':
  ajaxSaveTamProgress();
  break;

case 'user-tam-stimulus':
  UsertamStimulus();
  break;

case 'user-tam-submit':
    submitTAM();
    break;


    /* ======================
       ADMIN KRAEPLIN
       ======================*/
    case 'admin-kraeplin-settings':
        adminKraeplinSettings();
        break;

    case 'admin-kraeplin-settings-save':
        adminKraeplinSettingsSave();
        break;

 case 'admin-kraeplin-results':
    adminKraeplinResults();
    break;

case 'admin-kraeplin-result-detail':
    adminKraeplinResultDetail();
    break;

case 'admin-kraeplin-export':
    adminKraeplinExportExcel();
    break;

    /* ======================
   ADMIN ACTIVITY LOGS
   ======================*/
case 'admin-activity-list':
    adminActivityListPage();
    break;
case 'admin-activity-detail':
    adminActivityDetail();
    break;

// ... router lain
case 'user-tpa-test':
  userTpaTest();
  break;

case 'ajax-tpa-progress':
  ajaxSaveTpaProgress();
  break;

case 'user-tpa-start':
    userTPAStart();
    break;

case 'user-tpa-submit':
    userTpaSubmit();
    break;
// ... router lain

case 'user-kraeplin-start':
    userKraeplinStart();
    break;

case 'user-kraeplin-test':
    userKraeplinTest();
    break;

case 'user-kraeplin-submit':
    submitKraeplin();
    break;

    /* ======================
       DEFAULT / 404
       ======================*/
    default:
        echo "<h1>404 - Halaman tidak ditemukan</h1>";
        break;
}

