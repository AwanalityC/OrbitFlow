<?php

namespace Tests\Feature;

use Tests\TestCase;

class StudentResponsivePolishTest extends TestCase
{
    public function test_student_dashboard_and_catalog_text_can_wrap_without_ellipsis(): void
    {
        $css = file_get_contents(public_path('css/orbitflow-ui.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('.overview-card .overview-note {', $css);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $css);
        $this->assertStringContainsString('text-overflow: clip;', $css);
        $this->assertStringContainsString('white-space: normal;', $css);
        $this->assertStringContainsString('-webkit-line-clamp: unset;', $css);
    }

    public function test_student_borrowing_stepper_and_form_controls_have_mobile_layout_rules(): void
    {
        $css = file_get_contents(public_path('css/orbitflow-ui.css'));
        $form = file_get_contents(resource_path('views/siswa/borrowing-form.blade.php'));

        $this->assertIsString($css);
        $this->assertIsString($form);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) 14px minmax(0, 1fr) 14px minmax(0, 1fr);', $css);
        $this->assertStringContainsString('justify-items: center;', $css);
        $this->assertStringContainsString('.student-equipment-select {', $css);
        $this->assertStringContainsString('.student-date-field .student-form-control[type="date"]', $css);
        $this->assertStringContainsString('min-height: 48px;', $css);
        $this->assertStringNotContainsString('{{ $item->name }} ({{ $availableCount }} tersedia:', $form);
    }

    public function test_completed_with_loss_status_can_wrap_on_mobile_history_cards(): void
    {
        $css = file_get_contents(public_path('css/orbitflow-ui.css'));
        $history = file_get_contents(resource_path('views/siswa/borrowing-history.blade.php'));

        $this->assertIsString($css);
        $this->assertIsString($history);
        $this->assertStringContainsString('Selesai dengan kehilangan', $history);
        $this->assertStringContainsString('.student-history-table td[data-label="Status"] .status-chip', $css);
        $this->assertStringContainsString('white-space: normal;', $css);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $css);
    }

    public function test_student_sidebar_uses_accessible_svg_icons_instead_of_glyph_symbols(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/siswa.blade.php'));
        $css = file_get_contents(public_path('css/orbitflow-ui.css'));

        $this->assertIsString($layout);
        $this->assertIsString($css);
        $this->assertStringContainsString('aria-label="Dashboard"', $layout);
        $this->assertStringContainsString('aria-label="Daftar Alat"', $layout);
        $this->assertStringContainsString('aria-label="Peminjaman"', $layout);
        $this->assertStringContainsString('aria-label="Riwayat"', $layout);
        $this->assertSame(4, substr_count($layout, '<svg viewBox="0 0 24 24"'));
        $this->assertStringNotContainsString('<span class="nav-icon">▦</span>', $layout);
        $this->assertStringNotContainsString('<span class="nav-icon">▧</span>', $layout);
        $this->assertStringNotContainsString('<span class="nav-icon">▣</span>', $layout);
        $this->assertStringNotContainsString('<span class="nav-icon">▤</span>', $layout);
        $this->assertStringContainsString('.sidebar .nav-icon svg {', $css);
    }
}
