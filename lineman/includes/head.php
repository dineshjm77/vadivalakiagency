<head>
    <meta charset="utf-8" />
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : ''; ?>Lineman Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Lineman Work Dashboard" name="description" />
    <meta content="ecommer.in" name="author" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root{
            --lm-primary:#556ee6;
            --lm-dark:#2a3042;
            --lm-sidebar:#2f3649;
            --lm-muted:#74788d;
            --lm-light:#f8f8fb;
        }
        body{
            background:#f4f6fb;
            font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;
            color:#495057;
        }
        #layout-wrapper{min-height:100vh;}
        .vertical-menu{
            width:260px;
            position:fixed;
            top:0; left:0; bottom:0;
            background:var(--lm-sidebar);
            z-index:1001;
            overflow-y:auto;
            box-shadow:2px 0 12px rgba(0,0,0,.08);
        }
        .main-content{margin-left:260px;min-height:100vh;}
        .navbar-header{
            background:#fff;
            height:70px;
            box-shadow:0 1px 2px rgba(0,0,0,.06);
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:0 1rem;
        }
        .page-content{padding:24px;}
        .brand-box{
            height:70px;
            display:flex;
            align-items:center;
            padding:0 18px;
            color:#fff;
            font-weight:700;
            font-size:18px;
            border-bottom:1px solid rgba(255,255,255,.08);
        }
        .side-menu{list-style:none;margin:0;padding:16px 0;}
        .side-menu .menu-title{
            padding:12px 20px 6px;
            font-size:11px;
            letter-spacing:.08em;
            color:#a6b0cf;
            text-transform:uppercase;
        }
        .side-menu a{
            display:flex;
            align-items:center;
            gap:10px;
            color:#c3cbe4;
            text-decoration:none;
            padding:12px 20px;
            transition:.2s ease;
            font-size:14px;
        }
        .side-menu a:hover,.side-menu a.active{
            background:rgba(255,255,255,.08);
            color:#fff;
        }
        .page-title-box{
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom:20px;
        }
        .card{border:none;border-radius:14px;box-shadow:0 1px 3px rgba(15,23,42,.08);}
        .stat-card .icon{
            width:52px;height:52px;border-radius:12px;
            display:flex;align-items:center;justify-content:center;
            font-size:20px;
        }
        .table thead th{background:#f8fafc;color:#495057;font-size:13px;white-space:nowrap;}
        .table td{vertical-align:middle;}
        .badge-soft-primary{background:rgba(85,110,230,.15);color:#556ee6;}
        .badge-soft-success{background:rgba(52,195,143,.15);color:#34c38f;}
        .badge-soft-warning{background:rgba(241,180,76,.15);color:#f1b44c;}
        .badge-soft-danger{background:rgba(244,106,106,.15);color:#f46a6a;}
        .avatar-circle{
            width:38px;height:38px;border-radius:50%;
            background:#556ee6;color:#fff;display:flex;
            align-items:center;justify-content:center;font-weight:700;
        }
        .mobile-toggle{display:none;}
        @media (max-width: 991px){
            .vertical-menu{transform:translateX(-100%);transition:.25s ease;}
            .vertical-menu.show{transform:translateX(0);}
            .main-content{margin-left:0;}
            .mobile-toggle{display:inline-flex;}
        }
        @media print{
            .vertical-menu,.navbar-header,.page-title-right,.btn,.no-print,.modal{display:none !important;}
            .main-content{margin-left:0 !important;}
            .page-content{padding:0 !important;}
            body{background:#fff;}
            .card{box-shadow:none;}
        }
    </style>
</head>
