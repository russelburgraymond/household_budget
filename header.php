<?php
if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Budgeter II</title>
    <style>
        :root{
            --bg:#0b1220;
            --bg-accent:#0f172a;
            --surface:#111827;
            --surface-soft:#182234;
            --surface-muted:#1f2937;
            --text:#e5e7eb;
            --text-soft:#94a3b8;
            --border:#243041;
            --border-strong:#334155;
            --shadow:0 18px 40px rgba(0,0,0,.34);
            --shadow-soft:0 8px 18px rgba(0,0,0,.22);
            --nav:#020617;
            --primary:#3b82f6;
            --primary-hover:#2563eb;
            --secondary:#475569;
            --secondary-hover:#334155;
            --ghost:#1e293b;
            --ghost-hover:#273449;
            --success-bg:#0f2d1f;
            --success-border:#1f6b45;
            --error-bg:#3b1212;
            --error-border:#7f1d1d;
            --radius:14px;
            --radius-sm:10px;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0}
        body{
            font-family:Arial,Helvetica,sans-serif;
            background:radial-gradient(circle at top, #111b31 0%, var(--bg) 42%, #090f1c 100%);
            color:var(--text);
            line-height:1.4;
        }
        .wrap{max-width:1240px;margin:0 auto;padding:24px}
        .nav{background:rgba(2,6,23,.92);color:#fff;box-shadow:0 2px 12px rgba(0,0,0,.35);border-bottom:1px solid rgba(255,255,255,.05);backdrop-filter:blur(8px)}
        .nav .wrap{display:flex;gap:20px;align-items:center;padding-top:0;padding-bottom:0}
        .brand{font-size:1.05rem;font-weight:700;letter-spacing:.2px}
        .nav a{color:#cbd5e1;text-decoration:none;padding:16px 0;display:inline-block;opacity:.95}
        .nav a:hover{color:#fff;opacity:1}
        h1{font-size:2.1rem;margin:0 0 22px;color:#f8fafc}
        h2{font-size:1.9rem;margin:0 0 12px;color:#f8fafc}
        h3{font-size:1.35rem;margin:0 0 12px;color:#f8fafc}
        p{margin-top:0}
        label{display:block;font-size:.95rem;font-weight:600;color:#dbe4f0;margin-bottom:6px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 10px;border-bottom:1px solid rgba(255,255,255,.06);text-align:left;vertical-align:top}
        th{font-size:.9rem;color:#cbd5e1;background:#0f172a}
        input,select,textarea,button{font:inherit}
        input,select,textarea{
            width:100%;
            padding:10px 12px;
            border:1px solid var(--border-strong);
            border-radius:10px;
            background:#0f172a;
            color:var(--text);
            transition:border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }
        input:focus,select:focus,textarea:focus{
            outline:none;
            border-color:rgba(59,130,246,.8);
            box-shadow:0 0 0 3px rgba(59,130,246,.18);
            background:#111c2f;
        }
        input::placeholder,textarea::placeholder{color:#64748b}
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
        textarea{min-height:92px;resize:vertical}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;align-items:start}
        .grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;align-items:end}
        .card{
            background:linear-gradient(180deg, rgba(17,24,39,.96) 0%, rgba(15,23,42,.96) 100%);
            border:1px solid var(--border);
            border-radius:var(--radius);
            padding:18px 18px 20px;
            margin-bottom:18px;
            box-shadow:var(--shadow);
        }
        .card-header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:10px}
        .muted{color:var(--text-soft)}
        .small{font-size:.92rem}
        .actions{display:flex;gap:10px;flex-wrap:wrap}
        .button,.button:link,.button:visited{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            background:var(--primary);
            color:#fff;
            text-decoration:none;
            border:none;
            border-radius:10px;
            padding:10px 14px;
            cursor:pointer;
            font-weight:700;
            box-shadow:var(--shadow-soft);
            transition:background .18s ease, transform .08s ease, box-shadow .18s ease;
        }
        .button:hover{background:var(--primary-hover)}
        .button:active{transform:translateY(1px)}
        .button.secondary,.button.secondary:link,.button.secondary:visited{background:var(--secondary)}
        .button.secondary:hover{background:var(--secondary-hover)}
        .button.ghost,.button.ghost:link,.button.ghost:visited{background:var(--ghost);color:#e2e8f0}
        .button.ghost:hover{background:var(--ghost-hover)}
        .dropzone{
            min-height:150px;
            border:2px dashed var(--border-strong);
            border-radius:14px;
            padding:14px;
            background:linear-gradient(180deg,#0f172a 0%, #111827 100%);
            color:var(--text-soft);
            transition:border-color .2s ease, background .2s ease, box-shadow .2s ease;
        }
        .dropzone.is-over{
            border-color:var(--primary);
            background:#13203a;
            box-shadow:inset 0 0 0 1px rgba(59,130,246,.24), 0 0 0 4px rgba(59,130,246,.08);
        }
        .dropzone-empty{
            min-height:120px;
            display:flex;
            align-items:center;
            justify-content:center;
            text-align:center;
            font-size:1rem;
        }
        .draggable-row,.pay-item,.summary-box{
            background:linear-gradient(180deg, rgba(24,34,52,.95) 0%, rgba(18,27,42,.95) 100%);
            border:1px solid var(--border);
            border-radius:12px;
            padding:12px 13px;
            margin-bottom:10px;
            box-shadow:0 1px 2px rgba(0,0,0,.16);
        }
        .draggable-row{cursor:grab;transition:background .18s ease,border-color .18s ease,transform .12s ease,box-shadow .18s ease}
        .draggable-row:hover{background:#1b2940;border-color:#4b6b9a;box-shadow:0 10px 22px rgba(0,0,0,.22);transform:translateY(-1px)}
        .draggable-row:active{cursor:grabbing}
        .draggable-row.is-hidden{display:none}
        .bill-name{font-size:1.05rem;font-weight:700;margin-bottom:3px;color:#f8fafc}
        .bill-meta{font-size:.97rem;color:#cbd5e1}
        .bill-note{margin-top:6px;font-size:.92rem;color:var(--text-soft)}
        .pay-item-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
        .pay-item-actions{margin-top:10px}
        .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .row > *{flex:1}
        .flash{background:var(--success-bg);border:1px solid var(--success-border);padding:12px 14px;border-radius:10px;margin-bottom:16px;color:#dcfce7}
        .error{background:var(--error-bg);border-color:var(--error-border);color:#fee2e2}
        .totals{margin:12px 0 0;padding:12px 14px;background:#0f172a;border:1px solid var(--border);border-radius:12px;font-size:1.08rem;font-weight:700;color:#f8fafc}
        .section-title{margin:18px 0 10px}
        .helper-text{margin:0 0 14px;color:var(--text-soft);font-size:1rem}
        .mini-stat{padding:14px 16px;background:linear-gradient(180deg,#111827 0%,#0f172a 100%);border:1px solid var(--border);border-radius:12px}
        .mini-stat-label{font-size:.88rem;color:var(--text-soft);margin-bottom:4px}
        .mini-stat-value{font-size:1.2rem;font-weight:700;color:#f8fafc}
        .form-stack{display:flex;flex-direction:column;gap:12px}
        .upcoming-list-scroll{overflow-y:auto; padding-right:4px;}
        .upcoming-list-scroll::-webkit-scrollbar{width:10px}
        .upcoming-list-scroll::-webkit-scrollbar-track{background:#0f172a;border-radius:999px}
        .upcoming-list-scroll::-webkit-scrollbar-thumb{background:#334155;border-radius:999px}
        .upcoming-list-scroll::-webkit-scrollbar-thumb:hover{background:#475569}
        .table-card{padding-top:10px}

        .app-footer{margin-top:18px;padding-top:6px;text-align:center;font-size:.85rem;color:var(--text-soft)}
        .app-footer a{color:#cbd5e1;text-decoration:none}
        .app-footer a:hover{color:#fff;text-decoration:underline}
        @media (max-width: 980px){
            .grid,.grid-3{grid-template-columns:1fr}
            .wrap{padding:18px}
            h1{font-size:1.85rem}
            h2{font-size:1.55rem}
        }
    </style>
</head>
<body>
<div class="nav">
    <div class="wrap">
        <div class="brand">Budgeter II</div>
        <a href="index.php">Home</a>
        <a href="admin_bills.php">Recurring Bills</a>
        <a href="search.php">Search</a>
    </div>
</div>
<div class="wrap">
