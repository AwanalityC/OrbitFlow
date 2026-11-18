@extends('layouts.admin')

@section('title', 'Detail Aktivitas')
@section('page-title', 'Detail Aktivitas')
@section('page-description', 'Rincian tindakan administratif yang tercatat.')

@section('content')
    <section class="admin-page-intro">
        <div>
            <h2>{{ $activityLog->event_label }}</h2>
            <p>{{ $activityLog->summary }}</p>
        </div>
        <a href="{{ route('admin.activity-logs.index') }}" class="admin-btn">← Kembali ke log</a>
    </section>

    <section class="admin-panel activity-detail-panel">
        <div class="admin-panel-head">
            <div>
                <h3>Informasi aktivitas</h3>
                <p>Catatan ini dibuat otomatis oleh OrbitFlow dan tidak dapat diubah melalui aplikasi.</p>
            </div>
            <span class="admin-status active">{{ $activityLog->category_label }}</span>
        </div>
        <div class="admin-panel-body">
            <div class="admin-info-grid activity-detail-grid">
                <div class="admin-info-box activity-detail-item">
                    <span class="activity-detail-label">Pelaku</span>
                    <strong class="activity-detail-value">{{ $activityLog->actor_name }}</strong>
                    <small class="activity-detail-meta">{{ $activityLog->actor_email ?: 'Email tidak tersedia' }}</small>
                </div>
                <div class="admin-info-box activity-detail-item">
                    <span class="activity-detail-label">Peran saat tindakan</span>
                    <strong class="activity-detail-value">{{ $activityLog->actor_role_label }}</strong>
                </div>
                <div class="admin-info-box activity-detail-item">
                    <span class="activity-detail-label">Waktu</span>
                    <strong class="activity-detail-value">{{ $activityLog->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB</strong>
                </div>
                <div class="admin-info-box activity-detail-item">
                    <span class="activity-detail-label">Alamat IP</span>
                    <strong class="activity-detail-value">{{ $activityLog->ip_address ?: 'Tidak tersedia' }}</strong>
                </div>
                <div class="admin-info-box activity-detail-item">
                    <span class="activity-detail-label">Objek terkait</span>
                    <strong class="activity-detail-value">{{ $activityLog->subject_label ?: 'Tidak ada objek terkait' }}</strong>
                    @if ($activityLog->subject_type)
                        <small class="activity-detail-meta">{{ $activityLog->subject_type }}{{ $activityLog->subject_id ? ' #' . $activityLog->subject_id : '' }}</small>
                    @endif
                </div>
                <div class="admin-info-box activity-detail-item">
                    <span class="activity-detail-label">Kode tindakan</span>
                    <strong class="activity-detail-value activity-detail-code">{{ $activityLog->event_key }}</strong>
                </div>
            </div>
        </div>
    </section>

    @if ($activityLog->before_data)
        <section class="admin-panel admin-section-space">
            <div class="admin-panel-head">
                <div>
                    <h3>Data sebelum perubahan</h3>
                    <p>Ringkasan nilai yang tercatat sebelum tindakan. Data rahasia disaring sebelum disimpan.</p>
                </div>
            </div>
            <div class="admin-panel-body">
                <pre class="activity-json" aria-label="Data audit dalam format JSON">{{ json_encode($activityLog->before_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </section>
    @endif

    @if ($activityLog->after_data)
        <section class="admin-panel admin-section-space">
            <div class="admin-panel-head">
                <div>
                    <h3>Data setelah perubahan</h3>
                    <p>Ringkasan nilai setelah tindakan berhasil. Bagian ini membantu membandingkan perubahan.</p>
                </div>
            </div>
            <div class="admin-panel-body">
                <pre class="activity-json" aria-label="Data audit dalam format JSON">{{ json_encode($activityLog->after_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </section>
    @endif

    @if ($activityLog->user_agent)
        <section class="admin-panel admin-section-space">
            <div class="admin-panel-head">
                <div>
                    <h3>Informasi browser</h3>
                    <p>Informasi perangkat lunak browser yang dikirim oleh permintaan.</p>
                </div>
            </div>
            <div class="admin-panel-body">
                <p class="activity-user-agent">{{ $activityLog->user_agent }}</p>
            </div>
        </section>
    @endif
@endsection
