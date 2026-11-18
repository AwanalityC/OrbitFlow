<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'OrbitFlow')</title>
    <meta name="theme-color" content="#F4F7FC">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <style>
:root {
            --page: #f3f4f6;
            --surface: #ffffff;
            --border: #cbd1da;
            --text: #111827;
            --muted: #647084;
            --soft: #e5e7eb;
            --active: #e5e7eb;
            --accent: #111827;
            --success: #dfe4ea;
        }

        * { box-sizing: border-box; }

        html { min-height: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--page);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
        }

        a { color: inherit; }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 5;
            display: flex;
            flex-direction: column;
            width: 158px;
            background: var(--surface);
            border-right: 1px solid var(--border);
        }

        .brand {
            height: 54px;
            flex: 0 0 54px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 14px;
            border-bottom: 1px solid #e5e7eb;
        }

        .brand-mark {
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #94a3b8;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            background: #fff;
        }

        .brand-name {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: -.15px;
        }

        .nav-label {
            margin: 18px 14px 7px;
            color: #8791a0;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .35px;
        }

        .nav-list {
            display: grid;
            gap: 3px;
            padding: 0 12px;
        }

        .nav-link {
            min-height: 24px;
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 5px 7px;
            border: 1px solid transparent;
            border-radius: 4px;
            color: #344054;
            font-size: 10px;
            text-decoration: none;
        }

        .nav-link:hover { background: #f8fafc; }

        .nav-link.active {
            border-color: #bfc7d2;
            background: var(--active);
            color: #111827;
            font-weight: 700;
        }

        .nav-icon {
            width: 12px;
            flex: 0 0 12px;
            text-align: center;
            color: #4b5563;
            font-size: 11px;
            line-height: 1;
        }

        .sidebar-bottom {
            margin: auto 12px 12px;
            padding: 7px 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #e4e7ec;
            border-radius: 4px;
            background: #f8fafc;
            color: #667085;
            font-size: 8px;
        }

        .connection-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #10b981;
            flex: 0 0 5px;
        }

        .workspace {
            min-height: 100vh;
            margin-left: 158px;
        }

        .topbar {
            min-height: 54px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 7px 18px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .page-heading h1 {
            margin: 0 0 3px;
            font-size: 15px;
            line-height: 1.15;
            font-weight: 700;
        }

        .page-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 9px;
            line-height: 1.3;
        }

        .profile-area {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
        }

        .profile-copy { text-align: right; }
        .profile-name { font-size: 9px; font-weight: 700; }
        .profile-meta { margin-top: 2px; color: var(--muted); font-size: 8px; }

        .avatar {
            width: 25px;
            height: 25px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #bfc7d2;
            border-radius: 50%;
            background: #f8fafc;
            color: #344054;
            font-size: 9px;
        }

        .logout-form { margin: 0; }

        .logout-button {
            padding: 4px 6px;
            border: 0;
            background: transparent;
            color: #667085;
            font: inherit;
            font-size: 9px;
            cursor: pointer;
        }

        .content {
            width: 100%;
            max-width: 1500px;
            padding: 18px;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .filter-form {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .search-wrap {
            position: relative;
            width: 170px;
            flex: 0 1 170px;
        }

        .search-icon {
            position: absolute;
            top: 50%;
            left: 8px;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 12px;
            pointer-events: none;
        }

        .search-input,
        .filter-select {
            height: 28px;
            border: 1px solid #bfc7d2;
            border-radius: 4px;
            outline: none;
            background: #fff;
            color: #344054;
            font-family: inherit;
            font-size: 9px;
        }

        .search-input {
            width: 100%;
            padding: 0 8px 0 23px;
        }

        .filter-select {
            width: 105px;
            padding: 0 6px;
        }

        .filter-select.status-select { width: 94px; }
        .search-input:focus, .filter-select:focus { border-color: #7c8798; }

        .button {
            min-height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 0 10px;
            border: 1px solid #bfc7d2;
            border-radius: 4px;
            background: #fff;
            color: #1f2937;
            font-family: inherit;
            font-size: 9px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
        }

        .button:hover { background: #f8fafc; }

        .button.primary {
            border-color: var(--accent);
            background: var(--accent);
            color: #fff;
        }

        .button.primary:hover { background: #374151; }

        .catalog-title {
            margin: 16px 0 8px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.3;
        }

        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .equipment-card {
            min-width: 0;
            min-height: 154px;
            display: flex;
            flex-direction: column;
            padding: 8px;
            border: 1px solid #c3cad4;
            border-radius: 6px;
            background: #fff;
        }

        .equipment-image {
            position: relative;
            height: 73px;
            flex: 0 0 73px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid #c4cbd5;
            border-radius: 3px;
            background: #f3f4f6;
        }

        .equipment-image::before {
            position: absolute;
            inset: 0 auto 0 0;
            width: 48%;
            content: "";
            background: repeating-linear-gradient(36deg, transparent 0 6px, #cbd5e1 6px 6.7px, transparent 6.7px 11px);
            clip-path: polygon(0 0, 100% 100%, 0 100%);
            opacity: .8;
        }

        .image-label {
            position: relative;
            z-index: 1;
            padding: 2px 4px;
            border: 1px solid #cbd5e1;
            border-radius: 2px;
            background: #fff;
            color: #718096;
            font-size: 6px;
            letter-spacing: .15px;
        }

        .equipment-name {
            margin: 7px 0 2px;
            overflow-wrap: anywhere;
            font-size: 10px;
            line-height: 1.25;
            font-weight: 700;
        }

        .equipment-category {
            color: var(--muted);
            font-size: 8px;
            line-height: 1.25;
        }

        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: auto;
            padding-top: 10px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            max-width: 72%;
            min-height: 15px;
            padding: 2px 5px;
            overflow: hidden;
            border: 1px solid #c1c8d2;
            border-radius: 3px;
            background: #f3f4f6;
            color: #344054;
            font-size: 7px;
            line-height: 1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .status-badge.available { background: #e3e6eb; color: #111827; }
        .status-badge.borrowed { background: #fff; color: #475467; }
        .status-badge.damaged,
        .status-badge.unavailable,
        .status-badge.lost { background: #f3f4f6; color: #697586; }

        .view-button {
            min-width: 38px;
            min-height: 23px;
            padding: 0 8px;
            font-size: 8px;
        }

        .empty-state {
            padding: 24px 16px;
            border: 1px solid #c3cad4;
            border-radius: 6px;
            background: #fff;
            color: var(--muted);
            font-size: 11px;
            text-align: center;
        }

        @media (min-width: 1500px) {
            .content { max-width: 1500px; }
        }

        @media (max-width: 900px) {
            .sidebar { width: 140px; }
            .workspace { margin-left: 140px; }
            .content { padding: 14px; }
            .equipment-grid { gap: 8px; }
            .filter-form { gap: 5px; }
            .search-wrap { width: 145px; flex-basis: 145px; }
            .filter-select { width: 94px; }
            .filter-select.status-select { width: 88px; }
        }

        @media (max-width: 650px) {
            .sidebar { width: 58px; }
            .workspace { margin-left: 58px; }
            .brand { justify-content: center; padding: 0; }
            .brand-name, .nav-label, .nav-text, .status-text { display: none; }
            .nav-list { padding: 0 7px; }
            .nav-link { justify-content: center; padding: 8px 0; }
            .nav-icon { font-size: 13px; }
            .sidebar-bottom { justify-content: center; margin: auto 7px 9px; padding: 8px 0; }
            .topbar { padding: 8px 10px; gap: 6px; }
            .page-heading h1 { font-size: 13px; }
            .page-heading p { max-width: 180px; font-size: 8px; }
            .profile-copy { display: none; }
            .logout-button { padding: 3px; }
            .content { padding: 12px 10px; }
            .toolbar { align-items: stretch; flex-direction: column; }
            .filter-form { display: grid; grid-template-columns: minmax(0, 1fr) minmax(90px, .7fr); }
            .search-wrap { width: auto; flex-basis: auto; }
            .filter-select, .filter-select.status-select { width: 100%; }
            .toolbar > .button { align-self: flex-end; }
            .equipment-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 420px) {
            .equipment-grid { grid-template-columns: 1fr; }
            .equipment-card { min-height: 170px; }
            .equipment-image { height: 88px; flex-basis: 88px; }
        }
        #katalog { scroll-margin-top: 14px; }
        .breadcrumbs { margin: 0 0 12px; font-size: 9px; color: var(--muted); }
        .breadcrumbs a { text-decoration: none; }
        .breadcrumbs a:hover { text-decoration: underline; }
        .panel { min-width: 0; border: 1px solid var(--border); border-radius: 5px; background: var(--surface); }
        .panel-title { margin: 0; font-size: 10px; line-height: 1.3; font-weight: 700; }
        .panel-caption { margin: 4px 0 0; color: var(--muted); font-size: 8px; line-height: 1.4; }
        .panel-header { padding: 10px 11px 8px; border-bottom: 1px solid #e6e9ee; }
        .panel-body { padding: 10px 11px; }
        .alert-errors { margin: 0 0 12px; padding: 9px 11px; border: 1px solid #e5b8b8; border-radius: 4px; background: #fff7f7; color: #8a2727; font-size: 10px; }
        .alert-errors ul { margin: 5px 0 0; padding-left: 17px; }
        .field-label { display: block; margin: 0 0 5px; color: #344054; font-size: 8px; font-weight: 700; }
        .field-control { display: block; width: 100%; min-width: 0; height: 27px; padding: 5px 7px; border: 1px solid #c5ccd6; border-radius: 3px; outline: none; background: #fff; color: #344054; font-family: inherit; font-size: 9px; }
        .field-control:focus { border-color: #7c8798; box-shadow: 0 0 0 1px #e2e5e9; }
        textarea.field-control { height: auto; min-height: 65px; resize: vertical; line-height: 1.4; }
        .request-grid { display: grid; grid-template-columns: minmax(0, 1.18fr) minmax(260px, .82fr); align-items: start; gap: 10px; }
        .item-list { display: grid; gap: 7px; }
        .item-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 7px; padding: 7px; border: 1px solid #e0e4ea; border-radius: 4px; background: #fff; }
        .item-picker { min-width: 0; display: flex; align-items: center; gap: 7px; }
        .mini-equipment-icon { width: 22px; height: 22px; flex: 0 0 22px; display: flex; align-items: center; justify-content: center; border: 1px solid #cbd1da; border-radius: 3px; background: #f8fafc; color: #667085; font-size: 10px; }
        .item-select-wrap { min-width: 0; flex: 1; }
        .item-select-wrap .field-label { margin-bottom: 3px; }
        .equipment-select { width: 100%; min-width: 0; height: 25px; padding: 3px 5px; border: 1px solid #cbd1da; border-radius: 3px; background: white; color: #344054; font-family: inherit; font-size: 8px; }
        .quantity-control { display: flex; align-items: center; gap: 3px; white-space: nowrap; }
        .quantity-label { color: var(--muted); font-size: 8px; }
        .quantity-control button { width: 19px; height: 22px; padding: 0; border: 1px solid #cbd1da; border-radius: 3px; background: #fff; cursor: pointer; font-size: 10px; }
        .quantity-control input { width: 25px; height: 22px; padding: 2px; border: 1px solid #cbd1da; border-radius: 3px; text-align: center; font: inherit; font-size: 8px; }
        .remove-button { min-height: 22px; padding: 0 6px; border: 1px solid #cbd1da; border-radius: 3px; background: #fff; color: #344054; font: inherit; font-size: 8px; cursor: pointer; }
        .add-item-wrap { padding-top: 8px; text-align: center; }
        .button.secondary { background: #fff; color: var(--text); }
        .button.full { width: 100%; }
        .form-fields { display: grid; gap: 9px; }
        .success-area { min-height: calc(100vh - 94px); display: grid; place-items: center; padding: 4px 0 18px; }
        .success-card { width: min(100%, 365px); padding: 18px 20px; text-align: center; border: 1px solid var(--border); border-radius: 5px; background: #fff; }
        .success-icon { width: 28px; height: 28px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; border: 1px solid #344054; border-radius: 50%; font-size: 14px; }
        .success-title { margin: 0 0 5px; font-size: 11px; }
        .success-message { max-width: 285px; margin: 0 auto 10px; color: var(--muted); font-size: 8px; line-height: 1.5; }
        .status-chip { display: inline-flex; align-items: center; justify-content: center; min-height: 17px; padding: 2px 7px; border: 1px solid #c1c8d2; border-radius: 3px; background: #f8fafc; color: #344054; font-size: 7px; font-weight: 700; text-transform: uppercase; }
        .success-actions { display: grid; gap: 5px; margin-top: 14px; }
        .success-actions .button { width: 100%; min-height: 27px; font-size: 8px; }
        .status-overview { padding: 10px 11px; border: 1px solid var(--border); border-radius: 5px; background: #fff; }
        .overview-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .eyebrow { margin: 0 0 4px; color: var(--muted); font-size: 7px; letter-spacing: .3px; text-transform: uppercase; }
        .overview-heading { margin: 0; font-size: 10px; line-height: 1.35; }
        .timeline { position: relative; display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; margin: 14px 3px 1px; }
        .timeline:before { position: absolute; top: 8px; left: 16%; right: 16%; height: 1px; content: ''; background: #cbd1da; }
        .timeline-step { position: relative; z-index: 1; text-align: center; }
        .timeline-dot { width: 17px; height: 17px; margin: 0 auto 5px; display: flex; align-items: center; justify-content: center; border: 1px solid #cbd1da; border-radius: 50%; background: #fff; color: #667085; font-size: 8px; }
        .timeline-step.done .timeline-dot, .timeline-step.current .timeline-dot { border-color: #111827; background: #111827; color: #fff; }
        .timeline-label { font-size: 7px; font-weight: 700; }
        .timeline-sub { margin-top: 2px; color: var(--muted); font-size: 7px; }
        .status-detail-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, .88fr); gap: 10px; margin-top: 10px; }
        .detail-fields { display: grid; gap: 8px; }
        .detail-field { min-width: 0; }
        .detail-value { min-height: 25px; padding: 6px 7px; border-radius: 3px; background: #f3f4f6; color: #344054; font-size: 8px; line-height: 1.4; overflow-wrap: anywhere; }
        .borrowed-equipment-list { display: grid; gap: 6px; }
        .borrowed-equipment-row { display: flex; align-items: center; gap: 7px; padding: 6px; border: 1px solid #e1e5eb; border-radius: 4px; }
        .borrowed-equipment-copy { min-width: 0; flex: 1; }
        .borrowed-equipment-name { font-size: 8px; font-weight: 700; }
        .borrowed-equipment-category { margin-top: 2px; color: var(--muted); font-size: 7px; }
        .quantity-pill { min-width: 19px; padding: 4px; border: 1px solid #cbd1da; border-radius: 3px; text-align: center; font-size: 8px; }
        .status-bottom-actions { margin-top: 9px; display: flex; flex-wrap: wrap; gap: 6px; }
        .status-bottom-actions .button { font-size: 8px; }
        .history-toolbar { display: flex; align-items: center; gap: 7px; margin-bottom: 10px; }
        .history-search { width: 210px; max-width: 100%; }
        .history-filter { width: 112px; }
        .history-count { margin-left: auto; color: var(--muted); font-size: 8px; }
        .table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 5px; background: #fff; }
        .history-table { width: 100%; border-collapse: collapse; font-size: 8px; text-align: left; }
        .history-table th { padding: 8px 7px; background: #f8fafc; color: #667085; font-size: 7px; font-weight: 700; text-transform: uppercase; white-space: nowrap; }
        .history-table td { padding: 9px 7px; border-top: 1px solid #e7eaf0; vertical-align: middle; }
        .history-table tbody tr:hover { background: #fafbfc; }
        .history-id { font-weight: 700; white-space: nowrap; }
        .history-submitted { color: #344054; white-space: nowrap; }
        .history-submitted-time { display: block; margin-top: 3px; color: #98a2b3; font-size: 7px; }
        .history-period { color: #344054; white-space: nowrap; }
        .history-equipment { min-width: 140px; }
        .history-equipment-name { font-weight: 700; }
        .history-equipment-sub { margin-top: 3px; color: var(--muted); font-size: 7px; }
        .history-action { min-height: 23px; padding: 0 7px; font-size: 7px; }
        .empty-state.large { padding: 30px 18px; font-size: 10px; }
        .equipment-detail-card { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap: 12px; padding: 10px; }
        .detail-image-placeholder { position: relative; min-height: 255px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid #cbd1da; border-radius: 3px; background: #f3f4f6; }
        .detail-image-placeholder:before, .detail-image-placeholder:after { position: absolute; inset: 0; content: ''; pointer-events: none; }
        .detail-image-placeholder:before { background: linear-gradient(to bottom right, transparent calc(50% - .5px), #cbd1da 50%, transparent calc(50% + .5px)); }
        .detail-image-placeholder:after { background: linear-gradient(to top right, transparent calc(50% - .5px), #cbd1da 50%, transparent calc(50% + .5px)); }
        .detail-image-label { position: relative; z-index: 1; padding: 2px 5px; border: 1px solid #cbd1da; border-radius: 2px; background: #fff; color: #667085; font-size: 7px; }
        .equipment-detail-copy { min-width: 0; }
        .equipment-detail-title-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
        .equipment-detail-title { margin: 0 0 3px; font-size: 12px; }
        .equipment-detail-category { margin: 0; color: var(--muted); font-size: 8px; }
        .equipment-detail-description { margin: 10px 0; color: #475467; font-size: 8px; line-height: 1.55; }
        .info-heading { margin: 11px 0 5px; font-size: 9px; }
        .info-rows { border-top: 1px solid #e3e7ed; }
        .info-row { display: grid; grid-template-columns: 42% minmax(0, 1fr); gap: 8px; padding: 6px 0; border-bottom: 1px solid #e3e7ed; font-size: 8px; }
        .info-row-label { color: var(--muted); }
        .info-row-value { font-weight: 600; overflow-wrap: anywhere; }
        .detail-note { margin: 10px 0; padding: 7px 8px; border: 1px solid #e3e7ed; border-radius: 3px; background: #f8fafc; color: #475467; font-size: 7px; line-height: 1.45; }
        .detail-actions { display: grid; gap: 5px; }
        .detail-actions .button { width: 100%; min-height: 26px; font-size: 8px; }
        @media (max-width: 760px) {
            .request-grid, .status-detail-grid, .equipment-detail-card { grid-template-columns: 1fr; }
            .detail-image-placeholder { min-height: 210px; }
            .history-toolbar { flex-wrap: wrap; }
            .history-count { margin-left: 0; width: 100%; }
        }
        @media (max-width: 650px) {
            .item-row { grid-template-columns: minmax(0, 1fr) auto; }
            .quantity-control { grid-column: 1; grid-row: 2; padding-left: 29px; }
            .remove-button { grid-column: 2; grid-row: 1 / span 2; }
            .history-search { flex: 1 1 100%; width: 100%; }
            .history-filter { flex: 1; width: auto; }
            .history-table { min-width: 760px; }
        }
    </style>
    @stack('styles')

    <link rel="stylesheet" href="{{ asset('css/orbitflow-ui.css') }}?v=10">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-brand.css') }}?v=3">
</head>
<body>
    <aside class="sidebar">
        <a class="brand" href="{{ route('siswa.dashboard') }}" style="color:inherit;text-decoration:none">
            <img class="brand-mark-image" src="{{ asset('images/orbitflow-mark.svg') }}" alt="Logo OrbitFlow"><span class="brand-name">OrbitFlow</span>
        </a>
        <div class="nav-label">Menu Siswa</div>
        <nav class="nav-list" aria-label="Navigasi siswa">
            <a class="nav-link {{ request()->routeIs('siswa.dashboard') ? 'active' : '' }}" href="{{ route('siswa.dashboard') }}" aria-label="Dashboard" title="Dashboard">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/>
                        <rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/>
                        <rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/>
                        <rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>
                    </svg>
                </span><span class="nav-text">Dashboard</span>
            </a>
            <a class="nav-link {{ request()->routeIs('equipment.index', 'equipment.show') ? 'active' : '' }}" href="{{ route('equipment.index') }}" aria-label="Daftar Alat" title="Daftar Alat">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m12 3 8.5 4.5v9L12 21l-8.5-4.5v-9L12 3Z"/>
                        <path d="m3.8 7.7 8.2 4.4 8.2-4.4M12 12.1V21"/>
                    </svg>
                </span><span class="nav-text">Daftar Alat</span>
            </a>
            <a class="nav-link {{ request()->routeIs('borrowing.create', 'borrowing.success', 'borrowing.status') ? 'active' : '' }}" href="{{ route('borrowing.create') }}" aria-label="Peminjaman" title="Peminjaman">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="4.5" width="14" height="17" rx="2"/>
                        <path d="M9 4.5V3h6v1.5M8.5 11h7M8.5 15h5"/>
                    </svg>
                </span><span class="nav-text">Peminjaman</span>
            </a>
            <a class="nav-link {{ request()->routeIs('borrowing.history') ? 'active' : '' }}" href="{{ route('borrowing.history') }}" aria-label="Riwayat" title="Riwayat">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="8.5"/>
                        <path d="M12 7v5l3 2M4.5 5.5 3 4"/>
                    </svg>
                </span><span class="nav-text">Riwayat</span>
            </a>
        </nav>
        <div class="sidebar-bottom"><span class="connection-dot"></span><span class="status-text">Sistem Terhubung</span></div>
    </aside>

    <div class="workspace">
        <header class="topbar">
            <div class="page-heading">
                <h1>@yield('page-heading', 'OrbitFlow')</h1>
                <p>@yield('page-subtitle', 'Sistem Inventarisasi dan Peminjaman Alat Sekolah')</p>
            </div>
            <div class="profile-area">
                <div class="profile-copy">
                    <div class="profile-name">{{ auth()->user()->name }}</div>
                    <div class="profile-meta">Siswa / Peminjam</div>
                </div>
                <span class="avatar" aria-label="Avatar {{ auth()->user()->name }}">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <form class="logout-form" method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="logout-button">Keluar</button>
                </form>
            </div>
        </header>
        <main class="content">
            @yield('content')
        </main>
    </div>
    @stack('scripts')
    <script src="{{ asset('js/orbitflow-live-filters.js') }}?v=1" defer></script>
    <script src="{{ asset('js/orbitflow-camera-capture.js') }}?v=1" defer></script>
</body>
</html>
