<?php
// TaskManager Topbar Bileşeni
$currentUser = $_SESSION['user'] ?? null;
?>
<header class="cu-topbar">
    <div class="cu-topbar-left">
        <!-- Mobil Hamburger Menü Butonu -->
        <button type="button" class="cu-mobile-menu-btn" id="cuMobileMenuBtn" onclick="toggleMobileSidebar()" title="<?= htmlspecialchars(__('app.sidebar_menu_toggle')) ?>" aria-label="<?= htmlspecialchars(__('app.sidebar_menu_toggle')) ?>">
            <span>☰</span>
        </button>
        <a href="<?= baseUrl() ?>" class="cu-logo">
            <div class="cu-logo-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
            </div>
            <span>TaskManager</span>
        </a>
        <div class="cu-workspace-badge">
            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#00b894;"></span>
            <span>Workspace: <strong><?= __('app.layout_main_workspace') ?></strong></span>
        </div>
    </div>

    <div class="cu-topbar-center">
        <div class="cu-search-box" onclick="openCommandPalette()" style="cursor:pointer;">
            <span class="search-icon">🔍</span>
            <input type="text" id="cuGlobalSearch" placeholder="<?= htmlspecialchars(__('app.layout_search_placeholder')) ?>" autocomplete="off" readonly style="cursor:pointer;">
            <span style="position:absolute; right:10px; top:50%; transform:translateY(-50%); font-size:10px; font-weight:700; color:var(--text-muted); background:rgba(255,255,255,0.06); padding:2px 6px; border-radius:4px; border:1px solid var(--border-subtle);">Ctrl+K</span>
        </div>
    </div>

    <div class="cu-topbar-right">
        <!-- CANLI SSE / REALTIME SENKRONİZASYON DURUMU -->
        <div id="cuRealtimeStatus" class="cu-realtime-badge" 
             data-title-active="<?= htmlspecialchars(__('app.layout_realtime_active')) ?>"
             data-title-reconnecting="<?= htmlspecialchars(__('app.layout_realtime_reconnecting')) ?>"
             data-text-live="<?= htmlspecialchars(__('app.layout_realtime_badge')) ?>"
             data-text-connecting="<?= htmlspecialchars(__('app.layout_realtime_connecting')) ?>"
             title="<?= htmlspecialchars(__('app.layout_realtime_active')) ?>">
            <span class="cu-realtime-dot"></span>
            <span id="cuRealtimeText"><?= __('app.layout_realtime_badge') ?></span>
        </div>

        <!-- TEMA DEĞİŞTİRİCİ (LIGHT / DARK THEME TOGGLE) -->
        <button type="button" class="cu-theme-toggle-btn" id="cuThemeToggleBtn" onclick="toggleTheme()" title="<?= htmlspecialchars(__('app.layout_theme_toggle')) ?>" style="width:34px; height:34px; padding:0; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.04); border:1px solid var(--border-subtle); border-radius:var(--radius-sm); font-size:16px; cursor:pointer; transition:var(--transition);">
            <span id="cuThemeIcon">☀️</span>
        </button>

        <!-- HIZLI SOHBET BUTONU (TEAM CHAT) -->
        <?php if (\Core\ModuleLoader::isModuleActive('chat')): ?>
        <a href="<?= baseUrl('chat') ?>" class="cu-theme-toggle-btn" title="<?= htmlspecialchars(__('app.layout_quick_chat')) ?>" style="width:34px; height:34px; padding:0; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.04); border:1px solid var(--border-subtle); border-radius:var(--radius-sm); font-size:15px; text-decoration:none; color:inherit; transition:var(--transition);">
            💬
        </a>
        <?php endif; ?>

        <!-- BİLDİRİM ZİLİ (NOTIFICATIONS) -->
        <div style="position:relative;">
            <button class="cu-btn-sm" onclick="toggleNotificationsDropdown()" style="position:relative; width:34px; height:34px; padding:0; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.04); font-size:15px;" title="<?= htmlspecialchars(__('app.layout_notifications')) ?>">
                🔔
                <span id="notifBadge" style="display:none; position:absolute; top:-2px; right:-2px; width:8px; height:8px; border-radius:50%; background:#ff4757; border:2px solid var(--bg-sidebar);"></span>
            </button>

            <!-- Bildirim Açılır Paneli -->
            <div id="notifDropdown" style="display:none; position:absolute; right:0; top:42px; width:320px; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); box-shadow:var(--shadow-modal); z-index:1100; overflow:hidden;">
                <div style="padding:12px 16px; border-bottom:1px solid var(--border-subtle); display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:13px; font-weight:700; color:var(--text-white);">🔔 <?= __('app.layout_notifications') ?></span>
                    <span style="font-size:11px; color:var(--text-muted);"><?= __('app.layout_notif_recent') ?></span>
                </div>
                <div id="notifList" style="max-height:260px; overflow-y:auto; padding:8px 0;">
                    <div style="padding:16px; text-align:center; font-size:12px; color:var(--text-muted);"><?= __('app.layout_notif_loading') ?></div>
                </div>
            </div>
        </div>

        <?php if ($currentUser && $currentUser->role === 'admin'): ?>
            <button type="button" id="btnOpenNewProjectModal" class="cu-btn-quick-task" onclick="openNewProjectModal()" style="height:34px; padding:0 14px; border-radius:var(--radius-sm); font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px; border:1px solid var(--border-subtle); background:rgba(255,255,255,0.08); color:var(--text-white); box-shadow:none; cursor:pointer; transition:var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.12)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
                <span>📁</span>
                <span><?= __('app.layout_btn_project') ?></span>
            </button>
            <button type="button" class="cu-btn-quick-task" onclick="openNewTaskModal()">
                <span>➕</span>
                <span><?= __('app.layout_btn_new_task') ?></span>
            </button>
        <?php endif; ?>

        <?php if ($currentUser): ?>
            <!-- Profil & Rol Göstergesi Açılır Menü -->
            <div style="position:relative;">
                <div class="cu-user-profile" id="userProfileBtn" onclick="toggleUserDropdown(event)" style="cursor:pointer;" title="<?= htmlspecialchars(__('app.layout_user_menu')) ?>">
                    <div class="cu-avatar" style="background: <?= htmlspecialchars($currentUser->avatar_color ?? '#7B68EE') ?>;">
                        <?= mb_substr($currentUser->name ?? 'U', 0, 1) ?>
                    </div>
                    <div style="display:flex; flex-direction:column; line-height:1.2;">
                        <span style="font-size:12px; font-weight:600; color:var(--text-white);"><?= htmlspecialchars(explode(' ', $currentUser->name)[0]) ?></span>
                        <span style="font-size:10px; color:var(--text-muted); text-transform:uppercase;"><?= htmlspecialchars($currentUser->role) ?> ▾</span>
                    </div>
                </div>

                <!-- Kullanıcı Açılır Menüsü (User Dropdown) -->
                <div id="userDropdown" style="display:none; position:absolute; right:0; top:42px; width:220px; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); box-shadow:var(--shadow-modal); z-index:9999; overflow:hidden;">
                    <div style="padding:14px 16px; border-bottom:1px solid var(--border-subtle); background:rgba(255,255,255,0.02);">
                        <div style="font-size:13px; font-weight:700; color:var(--text-white);"><?= htmlspecialchars($currentUser->name ?? 'Kullanıcı') ?></div>
                        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;"><?= htmlspecialchars($currentUser->email ?? '') ?></div>
                        <span style="display:inline-block; margin-top:6px; font-size:10px; padding:2px 8px; border-radius:4px; font-weight:600; background:rgba(123, 104, 238, 0.15); color:#7B68EE; text-transform:uppercase;">
                            <?= ($currentUser->role === 'admin') ? __('app.layout_role_admin') : __('app.layout_role_member') ?>
                        </span>
                    </div>
                    <div style="padding:6px; display:flex; flex-direction:column; gap:2px;">
                        <button type="button" onclick="toggleTheme()" style="display:flex; align-items:center; justify-content:space-between; width:100%; padding:8px 12px; border-radius:var(--radius-sm); font-size:12.5px; font-weight:600; color:var(--text-primary); background:transparent; border:none; cursor:pointer; text-align:left; transition:var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.06)'" onmouseout="this.style.background='transparent'">
                            <span style="display:flex; align-items:center; gap:8px;">
                                <span id="userDropdownThemeIcon">☀️</span>
                                <span id="userDropdownThemeText"><?= __('app.layout_theme_light') ?></span>
                            </span>
                            <span id="userDropdownThemeBadge" style="font-size:10px; padding:2px 7px; border-radius:10px; background:rgba(123, 104, 238, 0.15); color:var(--brand-primary); font-weight:700;"><?= __('app.layout_theme_dark') ?></span>
                        </button>
                        <button type="button" onclick="openProfileModal(); toggleUserDropdown(event);" style="display:flex; align-items:center; gap:8px; width:100%; padding:8px 12px; border-radius:var(--radius-sm); font-size:12.5px; font-weight:600; color:var(--text-primary); background:transparent; border:none; cursor:pointer; text-align:left; transition:var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.06)'" onmouseout="this.style.background='transparent'">
                            <span>⚙️</span>
                            <span><?= __('app.layout_edit_profile') ?></span>
                        </button>
                        <?php if (($currentUser->role ?? '') === 'admin'): ?>
                        <a href="<?= baseUrl('settings') ?>" style="display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:var(--radius-sm); font-size:12.5px; font-weight:600; color:var(--text-primary); text-decoration:none; transition:var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.06)'" onmouseout="this.style.background='transparent'">
                            <span>🛠️</span>
                            <span><?= __('app.layout_system_settings') ?></span>
                        </a>
                        <?php endif; ?>
                        <?php if (\Core\ModuleLoader::isModuleActive('apitester')): ?>
                        <a href="<?= baseUrl('api-docs') ?>" style="display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:var(--radius-sm); font-size:12.5px; font-weight:600; color:#7B68EE; text-decoration:none; transition:var(--transition);" onmouseover="this.style.background='rgba(123,104,238,0.1)'" onmouseout="this.style.background='transparent'">
                            <span>⚡</span>
                            <span><?= __('app.layout_api_guide') ?></span>
                        </a>
                        <?php endif; ?>
                        <a href="<?= baseUrl('logout') ?>" style="display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:var(--radius-sm); font-size:12.5px; font-weight:600; color:#ff4757; text-decoration:none; transition:var(--transition);" onmouseover="this.style.background='rgba(255,71,87,0.1)'" onmouseout="this.style.background='transparent'">
                            <span>🚪</span>
                            <span><?= __('app.layout_logout') ?></span>
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <a href="<?= baseUrl('login') ?>" class="cu-btn-sm" style="background:var(--brand-primary); color:#fff; text-decoration:none; padding:6px 14px; font-weight:600;">
                <?= __('app.layout_login') ?>
            </a>
        <?php endif; ?>
    </div>
</header>
