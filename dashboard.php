<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

local_admindashboard_setup_page('/local/admindashboard/dashboard.php', 'Admin Dashboard', 'dashboard');
local_admindashboard_render_header('dashboard');
$sesskey = sesskey();
?>

<h2 class="mb-3">Dashboard</h2>

<div class="admindash-timer admindash-card" id="admindashTimerCard" aria-live="polite" aria-atomic="true" data-timer-state="loading">
    <div class="admindash-timer__copy">
        <div class="admindash-timer__eyebrow" id="admindashTimerEyebrow">Upcoming</div>
        <div class="admindash-timer__title" id="admindashTimerTitle">Loading&hellip;</div>
        <div class="admindash-timer__course" id="admindashTimerCourse" hidden></div>
        <div class="admindash-timer__meta" id="admindashTimerMeta"></div>
    </div>
    <div class="admindash-timer__countdown-wrap">
        <div class="admindash-timer__countdown-label">Time Remaining</div>
        <div class="admindash-timer__countdown" id="admindashTimerCountdown">&#8212;&#8212;</div>
    </div>
    <div class="admindash-timer__status">
        <div class="admindash-timer__chip">
            <span class="admindash-timer__chip-label">Type</span>
            <strong id="admindashTimerMode">&mdash;</strong>
        </div>
        <div class="admindash-timer__chip">
            <span class="admindash-timer__chip-label" id="admindashTimerScopeLabel">On</span>
            <strong id="admindashTimerScope">&mdash;</strong>
        </div>
    </div>
</div>

<div class="admindash-filters admindash-card">
    <div class="admindash-filters__header">
        <span class="admindash-filters__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.628 1.601C5.028 1.2 7.49 1 10 1s4.973.2 7.372.601a.75.75 0 0 1 .628.74v2.288a2.25 2.25 0 0 1-.659 1.59l-4.682 4.683a2.25 2.25 0 0 0-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 0 1 8 18.25v-5.757a2.25 2.25 0 0 0-.659-1.591L2.659 6.22A2.25 2.25 0 0 1 2 4.629V2.34a.75.75 0 0 1 .628-.74Z" clip-rule="evenodd"/></svg>
        </span>
        <span class="admindash-filters__title">Filters</span>
        <span class="admindash-filters__badge" id="activeFilterBadge" aria-label="active filters count"></span>
    </div>
    <div class="admindash-filters__sep" aria-hidden="true"></div>
    <div class="admindash-filters__fields">
        <div class="admindash-filter-group">
            <label class="admindash-filter-group__label" for="courseSelect">Course</label>
            <select id="courseSelect" class="form-select admindash-filter-group__select">
                <option value="0">All Courses</option>
            </select>
        </div>
        <div class="admindash-filter-group">
            <label class="admindash-filter-group__label" for="deptSelect">Department</label>
            <select id="deptSelect" class="form-select admindash-filter-group__select">
                <option value="">All Departments</option>
            </select>
        </div>
        <div class="admindash-filter-group">
            <label class="admindash-filter-group__label" for="moduleSelect">Module</label>
            <select id="moduleSelect" class="form-select admindash-filter-group__select">
                <option value="0">All Modules</option>
            </select>
        </div>
    </div>
    <button class="admindash-filters__reset" id="filterResetBtn" title="Clear all filters" style="display:none" type="button">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z"/></svg>
        Reset
    </button>
</div>

<!-- ── Platform Overview Stats ─────────────────────────────────────────── -->
<div class="admindash-stat-row" id="platformStatRow">
    <div class="admindash-stat s1">
        <span class="admindash-stat__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M7 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM14.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM1.615 16.428a1.224 1.224 0 0 1-.569-1.175 6.002 6.002 0 0 1 11.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 0 1 7 18a9.953 9.953 0 0 1-5.385-1.572ZM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 0 0-1.588-3.755 4.502 4.502 0 0 1 5.874 2.636.818.818 0 0 1-.36.98A7.465 7.465 0 0 1 14.5 16Z"/></svg></span>
        <div class="admindash-stat__body">
            <div class="admindash-stat__value" id="statTotalStudents">—</div>
            <div class="admindash-stat__label" id="statTotalStudentsLabel">Unique Learners</div>
        </div>
    </div>
    <div class="admindash-stat s2">
        <span class="admindash-stat__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.394 2.08a1 1 0 0 0-.788 0l-7 3a1 1 0 0 0 0 1.84L5.25 8.051a.999.999 0 0 1 .356-.257l4-1.714a1 1 0 1 1 .788 1.838L7.667 9.088l1.94.831a1 1 0 0 0 .787 0l7-3a1 1 0 0 0 0-1.838l-7-3ZM3.31 9.397 5 10.12v4.102a8.969 8.969 0 0 0-1.05-.174 1 1 0 0 1-.89-.89 11.115 11.115 0 0 1 .25-3.762ZM9.3 16.573A9.026 9.026 0 0 0 10 17a9.026 9.026 0 0 0 .7-.427V12.5L10 12l-.7.5v4.073Zm4.39-2.477a8.989 8.989 0 0 1-1.05.175 1 1 0 0 1-.89-.89 11.115 11.115 0 0 1 .25-3.762l1.69.723v3.754Z"/></svg></span>
        <div class="admindash-stat__body">
            <div class="admindash-stat__value" id="statActiveCourses">—</div>
            <div class="admindash-stat__label">Active Courses</div>
        </div>
    </div>
    <div class="admindash-stat s3">
        <span class="admindash-stat__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg></span>
        <div class="admindash-stat__body">
            <div class="admindash-stat__value" id="statCompletionRate">—</div>
            <div class="admindash-stat__label" id="statCompletionRateLabel">Completion Rate</div>
        </div>
    </div>
    <div class="admindash-stat s4">
        <span class="admindash-stat__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/></svg></span>
        <div class="admindash-stat__body">
            <div class="admindash-stat__value" id="statPendingModules">—</div>
            <div class="admindash-stat__label" id="statPendingModulesLabel">Modules Pending</div>
        </div>
    </div>
    <div class="admindash-stat s5">
        <span class="admindash-stat__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M11.983 1.907a.75.75 0 0 0-1.292-.657l-8.5 9.5A.75.75 0 0 0 2.75 12h6.572l-1.305 6.093a.75.75 0 0 0 1.292.657l8.5-9.5A.75.75 0 0 0 17.25 8h-6.572l1.305-6.093Z"/></svg></span>
        <div class="admindash-stat__body">
            <div class="admindash-stat__value" id="statActiveUsers">—</div>
            <div class="admindash-stat__label">Active (last 7 days)</div>
        </div>
    </div>
    <div class="admindash-stat s6">
        <span class="admindash-stat__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M5.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 0 0 .75-.75V3.75A.75.75 0 0 0 7.25 3h-1.5ZM12.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 0 0 .75-.75V3.75a.75.75 0 0 0-.75-.75h-1.5Z"/></svg></span>
        <div class="admindash-stat__body">
            <div class="admindash-stat__value" id="statInactiveUsers">—</div>
            <div class="admindash-stat__label">Not Active (7 days)</div>
        </div>
    </div>
</div>

<div class="admindash-kpi-section-head">
    <span class="admindash-kpi-section-head__label" id="kpiSectionLabel">Outcome KPIs</span>
</div>

<div class="admindash-kpis" id="kpiCardsGrid">
    <div class="admindash-card admindash-kpi k1" id="kpiParticipantsCard" data-kpi-metric="participants" data-kpi-label="Total Enrollment" role="button" tabindex="0" aria-label="Open Total Enrollment user list" style="order:1">
        <div class="admindash-kpi__head">
            <div class="label" id="kpiParticipantsLabel">Total Enrollment</div>
            <span class="admindash-kpi__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M7 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM14.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM1.615 16.428a1.224 1.224 0 0 1-.569-1.175 6.002 6.002 0 0 1 11.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 0 1 7 18a9.953 9.953 0 0 1-5.385-1.572ZM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 0 0-1.588-3.755 4.502 4.502 0 0 1 5.874 2.636.818.818 0 0 1-.36.98A7.465 7.465 0 0 1 14.5 16Z"/></svg></span>
        </div>
        <div class="value" id="kpiParticipants">...</div>
        <div id="kpiParticipantsTrend" class="admindash-kpi-trend-slot"></div>
    </div>
    <div class="admindash-card admindash-kpi k8" id="kpiTotalEnrollmentsCard" data-kpi-metric="total_enrollments" data-kpi-label="Current Total Enrollment" role="button" tabindex="0" aria-label="Open Current Total Enrollment list" style="order:3">
        <div class="admindash-kpi__head">
            <div class="label" id="kpiTotalEnrollmentsLabel">Current Total Enrollment</div>
            <span class="admindash-kpi__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M4.75 3A2.75 2.75 0 0 0 2 5.75v8.5A2.75 2.75 0 0 0 4.75 17h10.5A2.75 2.75 0 0 0 18 14.25v-8.5A2.75 2.75 0 0 0 15.25 3H4.75ZM4 14.25V9h12v5.25c0 .414.336.75.75.75h.75A2.75 2.75 0 0 1 15.25 17H4.75A2.75 2.75 0 0 1 2 14.25v-.75c0-.414.336-.75.75-.75H4Zm0-6.5V5.75c0-.414.336-.75.75-.75h10.5c.414 0 .75.336.75.75V7H4Z"/></svg></span>
        </div>
        <div class="value" id="kpiTotalEnrollments">...</div>
        <div class="kpi-pct admindash-kpi__pct" id="kpiTotalEnrollmentsPct"></div>
        <div class="admindash-kpi-trend-slot admindash-kpi-trend-slot--empty" aria-hidden="true"></div>
    </div>
    <div class="admindash-card admindash-kpi k5" id="kpiAttemptedCard" data-kpi-metric="attempted" data-kpi-label="Attempted" role="button" tabindex="0" aria-label="Open Attempted user list" style="display:none;order:4">
        <div class="admindash-kpi__head">
            <div class="label" id="kpiAttemptedLabel">Attempted</div>
            <span class="admindash-kpi__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 10a8 8 0 1 1 16 0 8 8 0 0 1-16 0Zm6.39-2.908a.75.75 0 0 1 .766.027l3.5 2.25a.75.75 0 0 1 0 1.262l-3.5 2.25A.75.75 0 0 1 8 12.25v-4.5a.75.75 0 0 1 .39-.658Z" clip-rule="evenodd"/></svg></span>
        </div>
        <div class="value" id="kpiAttempted">...</div>
        <div class="kpi-pct admindash-kpi__pct" id="kpiAttemptedPct"></div>
        <div id="kpiAttemptedTrend" class="admindash-kpi-trend-slot"></div>
    </div>
    <div class="admindash-card admindash-kpi k2" id="kpiPassedCard" data-kpi-metric="passed" data-kpi-label="Passed" role="button" tabindex="0" aria-label="Open Passed user list" style="order:6">
        <div class="admindash-kpi__head">
            <div class="label" id="kpiPassedLabel">Passed</div>
            <span class="admindash-kpi__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg></span>
        </div>
        <div class="value" id="kpiPassed">...</div>
        <div class="kpi-pct admindash-kpi__pct" id="kpiPassedPct"></div>
        <div id="kpiPassedTrend" class="admindash-kpi-trend-slot"></div>
    </div>
    <div class="admindash-card admindash-kpi k6" id="kpiCertifiedCard" data-kpi-metric="certified" data-kpi-label="Certificates Issued" role="button" tabindex="0" aria-label="Open Certificates Issued user list" style="order:7">
        <div class="admindash-kpi__head">
            <div class="label" id="kpiCertifiedLabel">Certificates Issued</div>
            <span class="admindash-kpi__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.394 2.08a1 1 0 0 0-.788 0l-7 3a1 1 0 0 0 0 1.84L5.25 8.051a.999.999 0 0 1 .356-.257l4-1.714a1 1 0 1 1 .788 1.838L7.667 9.088l1.94.831a1 1 0 0 0 .787 0l7-3a1 1 0 0 0 0-1.838l-7-3ZM3.31 9.397 5 10.12v4.102a8.969 8.969 0 0 0-1.05-.174 1 1 0 0 1-.89-.89 11.115 11.115 0 0 1 .25-3.762ZM9.3 16.573A9.026 9.026 0 0 0 10 17a9.026 9.026 0 0 0 .7-.427V12.5L10 12l-.7.5v4.073Zm4.39-2.477a8.989 8.989 0 0 1-1.05.175 1 1 0 0 1-.89-.89 11.115 11.115 0 0 1 .25-3.762l1.69.723v3.754Z"/></svg></span>
        </div>
        <div class="value" id="kpiCertified">...</div>
        <div class="kpi-pct admindash-kpi__pct" id="kpiCertifiedPct"></div>
        <div id="kpiCertifiedTrend" class="admindash-kpi-trend-slot"></div>
    </div>
    <div class="admindash-card admindash-kpi k3" id="kpiFailedCard" data-kpi-metric="failed" data-kpi-label="Failed" role="button" tabindex="0" aria-label="Open Failed user list" style="order:8">
        <div class="admindash-kpi__head">
            <div class="label">Failed</div>
            <span class="admindash-kpi__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd"/></svg></span>
        </div>
        <div class="value" id="kpiFailed">...</div>
        <div class="kpi-pct admindash-kpi__pct" id="kpiFailedPct"></div>
        <div id="kpiFailedTrend" class="admindash-kpi-trend-slot"></div>
    </div>
    <div class="admindash-card admindash-kpi k4" id="kpiDroppedCard" data-kpi-metric="not_attempted" data-kpi-label="Not Attempted" role="button" tabindex="0" aria-label="Open Not Attempted user list" style="order:5">
        <div class="admindash-kpi__head">
            <div class="label" id="kpiDroppedLabel">Not Attempted</div>
            <span class="admindash-kpi__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm-.75-11.25a.75.75 0 0 0-1.5 0v4.59L5.8 9.22a.75.75 0 0 0-1.1 1.02l3.25 3.5a.75.75 0 0 0 1.1 0l3.25-3.5a.75.75 0 1 0-1.1-1.02l-1.95 2.1V6.75Z" clip-rule="evenodd"/></svg></span>
        </div>
        <div class="value" id="kpiDropped">...</div>
        <div class="kpi-pct admindash-kpi__pct" id="kpiDroppedPct"></div>
        <div id="kpiDroppedTrend" class="admindash-kpi-trend-slot"></div>
    </div>
    <div class="admindash-card admindash-kpi k7" id="kpiResignedCard" data-kpi-metric="resigned_midcourse" data-kpi-label="Resigned Midway" role="button" tabindex="0" aria-label="Open Resigned Midway user list" style="order:2">
        <div class="admindash-kpi__head">
            <div class="label" id="kpiResignedLabel">Resigned Midway</div>
            <span class="admindash-kpi__ico" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM2.046 15.253c-.058.468.172.92.57 1.175A9.953 9.953 0 0 0 8 18c1.982 0 3.83-.574 5.384-1.572.398-.254.628-.707.57-1.175a6.001 6.001 0 0 0-11.908 0ZM14.5 8a.75.75 0 0 1 0 1.5h-4a.75.75 0 0 1 0-1.5h4Z"/></svg></span>
        </div>
        <div class="value" id="kpiResigned">0</div>
        <div class="kpi-pct admindash-kpi__pct" id="kpiResignedPct"></div>
        <div class="admindash-kpi-trend-slot"></div>
    </div>
</div>

<div id="kpiPieCard" class="admindash-card p-2 bg-white admindash-kpi-pie-card">
    <div class="d-flex align-items-start justify-content-between" style="gap:12px">
        <div>
            <h5 class="mb-1">KPI Distribution</h5>
            <div class="text-muted small">Breakdown of current KPI values for selected filters.</div>
        </div>
    </div>
    <div class="admindash-kpi-pie-card__charts" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:10px;align-items:stretch;width:100%">
        <div class="admindash-kpi-pie-card__chart-panel" style="min-width:0;overflow:hidden">
            <div class="admindash-kpi-pie-card__chart-title">All KPI Mix</div>
            <div class="admindash-kpi-pie-card__canvas-wrap">
                <canvas id="kpiPieChart" height="140"></canvas>
            </div>
        </div>
        <div class="admindash-kpi-pie-card__chart-panel" style="min-width:0;overflow:hidden">
            <div class="admindash-kpi-pie-card__chart-title">Passed vs Failed</div>
            <div class="admindash-kpi-pie-card__canvas-wrap">
                <canvas id="kpiPassFailPieChart" height="140"></canvas>
            </div>
            <div id="kpiPassFailPieEmptyMsg" class="text-muted small" style="display:none;margin-top:6px">No pass/fail data yet for the selected filters.</div>
        </div>
        <div class="admindash-kpi-pie-card__chart-panel" style="min-width:0;overflow:hidden">
            <div class="admindash-kpi-pie-card__chart-title">Enrollment Status</div>
            <div class="admindash-kpi-pie-card__canvas-wrap">
                <canvas id="kpiEnrollmentStatusPieChart" height="140"></canvas>
            </div>
            <div id="kpiEnrollmentStatusPieEmptyMsg" class="text-muted small" style="display:none;margin-top:6px">No enrollment status data yet for the selected filters.</div>
        </div>
    </div>
    <div id="kpiPieEmptyMsg" class="text-muted small" style="display:none;margin-top:6px">No KPI data yet for the selected filters.</div>
</div>

<div class="modal fade" id="kpiUsersModal" tabindex="-1" aria-labelledby="kpiUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="kpiUsersModalLabel">KPI Users</h5>
                    <div class="text-muted small" id="kpiUsersModalMeta">Loading…</div>
                </div>
                <button type="button" class="btn-close" data-admindash-close-modal="1" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="admindash-modal-search">
                    <input type="search" class="form-control" id="kpiUsersSearch" placeholder="Search by name, department, or clinic" aria-label="Search KPI users">
                    <div class="admindash-modal-search__meta text-muted small" id="kpiUsersSearchMeta"></div>
                </div>
                <div id="kpiUsersModalEmpty" class="alert alert-light border" style="display:none;margin-bottom:16px">No users found for the selected KPI.</div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0" id="kpiUsersTable">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Department</th>
                                <th scope="col">Clinic Name</th>
                                <th scope="col" id="kpiUsersThEnrol" class="admindash-kpi-users-col-enrol" style="display:none">Course / Enrolment</th>
                            </tr>
                        </thead>
                        <tbody id="kpiUsersTableBody">
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                                    <div class="mt-2 text-muted small">Loading users…</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer admindash-modal-footer">
                <div class="admindash-modal-footer__status text-muted small" id="kpiReminderStatus"></div>
                <button type="button" class="btn btn-primary" id="kpiReminderButton" style="display:none">Send Reminder to All</button>
            </div>
        </div>
    </div>
</div>

<div class="admindash-charts">
    <div class="admindash-charts__column admindash-charts__column--left">
        <div class="admindash-card p-2 bg-white">
            <div class="d-flex align-items-center justify-content-between" style="gap:12px">
                <h5 class="mb-2" id="barChartTitle">Course Completion by Department</h5>
                <select id="barMetricSelect" class="form-select" style="max-width:190px">
                    <option value="completion">Completion %</option>
                    <option value="pass">Pass %</option>
                    <option value="fail">Fail %</option>
                    <option value="notattempted">Not Attempted %</option>
                </select>
            </div>
            <canvas id="barChart" height="120"></canvas>
            <div id="barEmptyMsg" class="text-muted small" style="display:none;margin-top:6px">No data yet for the selected filters.</div>
        </div>
        <div class="admindash-widget-grid">
            <div class="admindash-card p-2 bg-white admindash-widget-card">
                <div class="admindash-widget-card__header">
                    <div>
                        <h5 class="mb-1">Live Feed</h5>
                        <div class="text-muted small">Latest user actions across the selected course and filters.</div>
                    </div>
                </div>
                <div id="activityFeedWrap" class="admindash-livefeed">
                    <div class="admindash-widget-empty">Loading live feed…</div>
                </div>
            </div>
            <div class="admindash-card p-2 bg-white admindash-widget-card">
                <div class="admindash-widget-card__header">
                    <div>
                        <h5 class="mb-1">Course Completion</h5>
                        <div class="text-muted small">Top 5 recent courses, or selected-course modules by schedule progress.</div>
                    </div>
                </div>
                <div id="courseProgressWrap" class="admindash-courseprogress">
                    <div class="admindash-widget-empty">Loading course completion…</div>
                </div>
            </div>
        </div>
        <?php echo local_admindashboard_render_course_schedule_sticky_board(); ?>
    </div>
    <div class="admindash-charts__column admindash-charts__column--right">
        <div id="coursesOverviewCard" class="admindash-card p-2 bg-white admindash-overview-leaders">
            <div class="admindash-overview-leaders__header">
                <div class="admindash-overview-leaders__heading">
                    <h5 class="mb-1">Overall Course Leaders</h5>
                    <div class="text-muted small">Compare top participants and clinics across selected courses.</div>
                </div>
                <div class="admindash-segmented" role="group" aria-label="Overall course leader view">
                    <button type="button" class="admindash-segmented__button is-active" data-overview-leader-mode="participants">Participants</button>
                    <button type="button" class="admindash-segmented__button" data-overview-leader-mode="clinics">Clinics</button>
                </div>
            </div>
            <div class="admindash-overview-leaders__toolbar">
                <div class="admindash-overview-leaders__field">
                    <label class="admindash-overview-leaders__label" for="overviewCourseLeaderSelect">Courses</label>
                    <div class="admindash-multiselect" id="overviewCourseLeaderPicker">
                        <button type="button" class="admindash-multiselect__button" id="overviewCourseLeaderDropdownBtn" aria-expanded="false" aria-controls="overviewCourseLeaderMenu">
                            <span id="overviewCourseLeaderDropdownText">Loading courses...</span>
                            <span class="admindash-multiselect__chevron" aria-hidden="true"></span>
                        </button>
                        <div class="admindash-multiselect__menu" id="overviewCourseLeaderMenu" hidden>
                            <div class="admindash-multiselect__actions">
                                <button type="button" data-overview-course-action="all">Select all</button>
                                <button type="button" data-overview-course-action="clear">Clear</button>
                            </div>
                            <div class="admindash-multiselect__options" id="overviewCourseLeaderOptions"></div>
                        </div>
                    </div>
                    <select id="overviewCourseLeaderSelect" class="admindash-overview-leaders__select-native" multiple aria-hidden="true" tabindex="-1"></select>
                    <div class="admindash-overview-leaders__hint">Leave empty to rank all active courses.</div>
                </div>
                <div class="admindash-overview-leaders__summary" id="coursesOverviewSummary" aria-live="polite">
                    <div class="admindash-overview-leaders__summary-value">All</div>
                    <div class="admindash-overview-leaders__summary-label">courses selected</div>
                </div>
            </div>
            <div class="admindash-overview-leaders__legend" aria-hidden="true">
                <span class="admindash-overview-leaders__legend-item is-strong">70%+</span>
                <span class="admindash-overview-leaders__legend-item is-steady">40-69%</span>
                <span class="admindash-overview-leaders__legend-item is-watch">Below 40%</span>
            </div>
            <div id="coursesOverviewWrap" class="admindash-overview-leaders__board">
                <div id="coursesOverviewTop" class="admindash-overview-leaders__top"></div>
                <div id="coursesOverviewRest" class="admindash-overview-leaders__rest"></div>
            </div>
            <div id="coursesOverviewEmpty" class="admindash-overview-leaders__empty" style="display:none">No course leader data available yet.</div>
        </div>
        <div id="performanceCard" class="admindash-card p-2 bg-white">
            <div class="d-flex align-items-center justify-content-between" style="gap:12px">
                <div>
                    <h5 class="mb-1">Participant Performance (Leaders)</h5>
                    <div class="text-muted small">Switch between top participants and clinic-level leaders.</div>
                </div>
                <div class="admindash-segmented" role="group" aria-label="Participant performance view">
                    <button type="button" class="admindash-segmented__button is-active" data-performance-mode="participants">Participants</button>
                    <button type="button" class="admindash-segmented__button" data-performance-mode="clinics">Clinics</button>
                </div>
            </div>
            <canvas id="performanceChart" height="160"></canvas>
            <div id="performanceEmptyMsg" class="text-muted small" style="display:none;margin-top:6px">Select a course to view leading participants.</div>
        </div>
        <div class="admindash-card p-2 bg-white admindash-chart-card admindash-chart-card--content">
            <h5 class="mb-2" id="trendChartTitle">Content Engagement (30 days)</h5>
            <canvas id="enrolChart" height="120"></canvas>
            <div id="enrolEmptyMsg" class="text-muted small" style="display:none;margin-top:6px">No data yet for the selected filters.</div>
        </div>
        <div id="skillGapCard" class="admindash-card p-2 bg-white admindash-chart-card admindash-chart-card--radar">
            <h5 class="mb-2">Skill Gap Analysis</h5>
            <div class="admindash-chart-card__canvas-wrap">
                <canvas id="skillGapChart" height="120"></canvas>
            </div>
            <div id="skillGapEmptyMsg" class="text-muted small" style="display:none;margin-top:6px">No skill data yet for the selected course.</div>
        </div>
    </div>
</div>

<div id="atRiskSection" class="admindash-card admindash-risk-panel">
    <div class="admindash-risk-panel__header">
        <div>
            <h5 class="mb-1">At-Risk Participants</h5>
            <div id="atRiskMeta" class="text-muted small">Risk scan loading…</div>
        </div>
        <button type="button" class="btn btn-danger" id="atRiskReminderButton" style="display:none">Send Reminder to At-Risk</button>
    </div>
    <div id="atRiskEmptyMsg" class="alert alert-light border admindash-risk-empty" style="display:none">No participants currently match the at-risk rules for the selected filters.</div>
    <div id="atRiskReminderStatus" class="text-muted small admindash-risk-status"></div>
    <div class="table-responsive" id="atRiskTableWrap">
        <table class="table table-striped table-hover align-middle mb-0" id="atRiskTable">
            <thead>
                <tr>
                    <th scope="col">Participant</th>
                    <th scope="col">Department</th>
                    <th scope="col">Course</th>
                    <th scope="col">Risk Signals</th>
                </tr>
            </thead>
            <tbody id="atRiskTableBody">
                <tr>
                    <td colspan="4" class="text-center py-4 text-muted small">Loading at-risk participants…</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="atRiskDetailsModal" tabindex="-1" aria-labelledby="atRiskDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="atRiskDetailsModalLabel">At-Risk Details</h5>
                    <div class="text-muted small" id="atRiskDetailsMeta">Participant risk summary</div>
                </div>
                <button type="button" class="btn-close" data-admindash-close-risk-modal="1" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="atRiskDetailsBody"></div>
            </div>
        </div>
    </div>
</div>
<script>
(function waitForRequire() {
    if (typeof require !== 'function') {
        window.setTimeout(waitForRequire, 50);
        return;
    }
    require(['core/chartjs', 'core/notification'], function(Chart, Notification) {
let barChartInstance = null;
let performanceChartInstance = null;
let enrolChartInstance = null;
let skillGapChartInstance = null;
let kpiPieChartInstance = null;
let kpiPassFailPieChartInstance = null;
let kpiEnrollmentStatusPieChartInstance = null;
let feedbackSentimentChartInstance = null;
let dashboardViewMode = 'overview'; // 'overview' | 'filtered'
const numberFmt = new Intl.NumberFormat('en-US');
const adminDashSesskey = <?php echo json_encode($sesskey); ?>;
let dashboardState = { courseid: 0, department: '', moduleid: 0, isQuiz: false };
let performanceState = { leaderboard: null, mode: 'participants' };
let overviewLeaderState = { leaderboard: null, mode: 'participants' };
let liveFeedState = { loading: false, requestId: 0, intervalId: null, refreshEveryMs: 45000, nextRefreshAt: 0, countdownIntervalId: null };
let kpiUsersModal = null;
let atRiskDetailsModal = null;
let lastModalFocus = null;
let kpiModalState = { metric: '', label: '', count: 0, loading: false };
let atRiskState = { rows: [], courseRunning: false, loading: false };
let feedbackInsightsState = { loading: false, lastCourseId: 0, requestId: 0 };
let kpiModalUsers = [];
let kpiModalMetricKey = '';

/** Show Course / Enrolment column: all enrolments on platform; per-course detail for learner KPIs. */
function kpiModalShowsCourseEnrolColumn() {
    const key = kpiModalMetricKey;
    if (key === 'total_enrollments') {
        return true;
    }
    const courseId = Number(dashboardState.courseid || 0);
    const detailKeys = [
        'participants',
        'passed',
        'failed',
        'dropped_midway',
        'not_attempted',
        'notattempted',
        'attempted',
        'certified',
        'resigned_midcourse'
    ];
    if (detailKeys.indexOf(key) === -1) {
        return false;
    }
    if (courseId > 0) {
        return true;
    }
    // Platform overview: show course names for KPI drill-downs that return them in each row.
    const overviewCourseCol = ['passed', 'failed', 'dropped_midway', 'not_attempted', 'notattempted', 'attempted'];
    return overviewCourseCol.indexOf(key) !== -1;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function(ch) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch] || ch;
    });
}

function getThemeCssVar(name, fallback) {
    const root = document.body || document.documentElement;
    const value = root ? getComputedStyle(root).getPropertyValue(name).trim() : '';
    return value || fallback;
}

function getInitials(name) {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) {
        return 'NA';
    }
    return parts.slice(0, 2).map(function(part) {
        return part.charAt(0).toUpperCase();
    }).join('');
}

function getLiveFeedAvatarMarkup(type) {
    const avatarType = (type === 'female' || type === 'male') ? type : 'neutral';
    const palettes = {
        male: {
            bg1: '#38bdf8', bg2: '#2563eb', skin: '#f6c7a3', hair: '#0f172a', shirt: '#1d4ed8'
        },
        female: {
            bg1: '#f472b6', bg2: '#db2777', skin: '#f6c7a3', hair: '#7c2d12', shirt: '#be185d'
        },
        neutral: {
            bg1: '#2dd4bf', bg2: '#0f766e', skin: '#f6c7a3', hair: '#334155', shirt: '#0f766e'
        }
    };
    const palette = palettes[avatarType];
    const femaleHair = avatarType === 'female'
        ? '<path d="M20 13c0-4.5 3.8-8 8.5-8S37 8.5 37 13v6c0 1.2-.9 2.1-2.1 2.1H22.1c-1.2 0-2.1-.9-2.1-2.1v-6Z" fill="' + palette.hair + '"/><path d="M20.5 20.5c0 7 5.8 12.7 8 12.7s8-5.7 8-12.7c0-1.1-.9-2-2-2H22.5c-1.1 0-2 .9-2 2Z" fill="' + palette.hair + '" opacity=".18"/>'
        : '<path d="M20 14c.7-5.1 4.7-8.7 8.8-8.7 4.5 0 8 3.4 8.3 8.7l-3.5-2.7-2.6 2.4-2.7-2.2-3 2.5L20 14Z" fill="' + palette.hair + '"/>';
    const svg = ''
        + '<svg viewBox="0 0 58 58" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">'
        + '<defs><linearGradient id="feedAvatarBg-' + avatarType + '" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="' + palette.bg1 + '"/><stop offset="100%" stop-color="' + palette.bg2 + '"/></linearGradient></defs>'
        + '<rect x="1.5" y="1.5" width="55" height="55" rx="18" fill="url(#feedAvatarBg-' + avatarType + ')"/>'
        + '<circle cx="29" cy="24" r="9.3" fill="' + palette.skin + '"/>'
        + femaleHair
        + '<path d="M17 46c1.7-7.1 7.1-11.6 12-11.6S39.3 38.9 41 46" fill="' + palette.shirt + '"/>'
        + '<path d="M23 36.8c1.9 1.6 3.9 2.3 6 2.3s4.1-.7 6-2.3" stroke="#fff" stroke-opacity=".42" stroke-width="1.6" stroke-linecap="round"/>'
        + '<circle cx="25.8" cy="23.8" r="1" fill="#1f2937"/><circle cx="32.2" cy="23.8" r="1" fill="#1f2937"/><path d="M26.2 28.3c1.1 1.1 4.5 1.1 5.6 0" stroke="#b45309" stroke-width="1.3" stroke-linecap="round" fill="none"/>'
        + '</svg>';
    return svg;
}

function renderKpiTrend(slotId, trend) {
    const slot = document.getElementById(slotId);
    if (!slot) {
        return;
    }

    if (!trend || trend.supported === false) {
        slot.innerHTML = '';
        return;
    }

    const cssClass = String(trend.css_class || 'is-flat');
    const arrow = escapeHtml(trend.arrow || '→');
    const displayValue = escapeHtml(trend.display_value || '0%');
    const comparisonLabel = escapeHtml(trend.comparison_label || 'vs previous 30 days');

    slot.innerHTML = ''
        + '<div class="admindash-kpi-trend ' + cssClass + '" title="' + comparisonLabel + '">'
        + '<span class="admindash-kpi-trend__arrow" aria-hidden="true">' + arrow + '</span>'
        + '<span class="admindash-kpi-trend__value">' + displayValue + '</span>'
        + '<span class="admindash-kpi-trend__meta">' + comparisonLabel + '</span>'
        + '</div>';
}

function renderKpiPieChart(data, isQuiz, hasCourse) {
    const canvas = document.getElementById('kpiPieChart');
    const passFailCanvas = document.getElementById('kpiPassFailPieChart');
    const enrollmentStatusCanvas = document.getElementById('kpiEnrollmentStatusPieChart');
    const empty = document.getElementById('kpiPieEmptyMsg');
    const passFailEmpty = document.getElementById('kpiPassFailPieEmptyMsg');
    const enrollmentStatusEmpty = document.getElementById('kpiEnrollmentStatusPieEmptyMsg');
    if (!canvas) {
        return;
    }

    const labels = [];
    const values = [];
    const colors = [];

    labels.push(hasCourse ? 'Total Enrollment' : 'Unique Learners');
    values.push(Number(data.participants || 0));
    colors.push('#12c2b3');

    labels.push('Resigned Midway');
    values.push(Number(data.resigned_midcourse || 0));
    colors.push('#ea580c');

    labels.push(hasCourse ? 'Current Total Enrollment' : 'Course Enrollments');
    values.push(Number(data.total_enrollments || 0));
    colors.push('#0ea5e9');

    labels.push('Attempted');
    values.push(Number(data.attempted || 0));
    colors.push('#2563eb');

    const notAttVal = data.not_attempted != null && data.not_attempted !== ''
        ? Number(data.not_attempted)
        : Math.max(0, Number(data.total_enrollments || 0) - Number(data.attempted || 0));
    labels.push('Not Attempted');
    values.push(notAttVal);
    colors.push('#ef4444');

    labels.push('Passed');
    values.push(Number(data.passed || 0));
    colors.push('#16a34a');

    labels.push('Certificates Issued');
    values.push(Number(data.certified || 0));
    colors.push('#ec4899');

    labels.push('Failed');
    values.push(Number(data.failed || 0));
    colors.push('#7c3aed');

    const hasValues = values.some(function(value) {
        return Number(value) > 0;
    });
    const total = values.reduce(function(sum, value) {
        return sum + Number(value || 0);
    }, 0);

    if (empty) {
        empty.style.display = hasValues ? 'none' : 'block';
    }

    if (kpiPieChartInstance) {
        kpiPieChartInstance.destroy();
        kpiPieChartInstance = null;
    }
    if (kpiPassFailPieChartInstance) {
        kpiPassFailPieChartInstance.destroy();
        kpiPassFailPieChartInstance = null;
    }
    if (kpiEnrollmentStatusPieChartInstance) {
        kpiEnrollmentStatusPieChartInstance.destroy();
        kpiEnrollmentStatusPieChartInstance = null;
    }

    if (!hasValues) {
        if (passFailEmpty) {
            passFailEmpty.style.display = 'block';
        }
        if (enrollmentStatusEmpty) {
            enrollmentStatusEmpty.style.display = 'block';
        }
        return;
    }

    const piePercentLabelsPlugin = {
        id: 'admindashPiePercentLabels',
        afterDatasetsDraw: function(chart) {
            const dataset = chart.data && chart.data.datasets ? chart.data.datasets[0] : null;
            const meta = chart.getDatasetMeta(0);
            if (!dataset || !meta || !meta.data || !meta.data.length) {
                return;
            }

            const localTotal = (dataset.data || []).reduce(function(sum, value) {
                return sum + Number(value || 0);
            }, 0);
            if (localTotal <= 0) {
                return;
            }

            const ctx = chart.ctx;
            ctx.save();
            ctx.font = '700 12px "Segoe UI", sans-serif';
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            meta.data.forEach(function(arc, index) {
                const raw = Number(dataset.data[index] || 0);
                if (raw <= 0) {
                    return;
                }
                const percent = (raw / localTotal) * 100;
                if (percent < 4) {
                    return;
                }

                const position = arc.tooltipPosition();
                ctx.fillText(Math.round(percent) + '%', position.x, position.y);
            });

            ctx.restore();
        }
    };

    const kpiPieLegendOptions = {
        position: 'left',
        align: 'center',
        labels: {
            boxWidth: 10,
            padding: 10,
            usePointStyle: true,
            pointStyle: 'circle',
            font: {
                size: 11,
                weight: '700'
            }
        }
    };

    kpiPieChartInstance = new Chart(canvas.getContext('2d'), {
        type: 'pie',
        plugins: [piePercentLabelsPlugin],
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderColor: '#ffffff',
                borderWidth: 2,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: kpiPieLegendOptions,
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = Number(context.parsed || 0);
                            const percent = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                            return `${context.label}: ${numberFmt.format(value)} (${percent}%)`;
                        }
                    }
                }
            }
        }
    });

    const passFailLabels = ['Passed', 'Failed'];
    const passFailValues = [
        Number(data.passed || 0),
        Number(data.failed || 0)
    ];
    const passFailColors = ['#16a34a', '#7c3aed'];
    const passFailHasValues = passFailValues.some(function(value) {
        return Number(value) > 0;
    });
    const passFailTotal = passFailValues.reduce(function(sum, value) {
        return sum + Number(value || 0);
    }, 0);

    if (passFailEmpty) {
        passFailEmpty.style.display = passFailHasValues ? 'none' : 'block';
    }

    if (passFailCanvas && passFailHasValues) {
        kpiPassFailPieChartInstance = new Chart(passFailCanvas.getContext('2d'), {
            type: 'pie',
            plugins: [piePercentLabelsPlugin],
            data: {
                labels: passFailLabels,
                datasets: [{
                    data: passFailValues,
                    backgroundColor: passFailColors,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: kpiPieLegendOptions,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = Number(context.parsed || 0);
                                const percent = passFailTotal > 0 ? ((value / passFailTotal) * 100).toFixed(1) : '0.0';
                                return `${context.label}: ${numberFmt.format(value)} (${percent}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    const totalEnrollmentRecords = Number(data.total_enrollments || data.participants || 0);
    const notStarted = Math.max(0, Number(notAttVal || 0));
    const resigned = Math.max(0, Number(data.resigned_midcourse || 0));
    const startedActive = Math.max(0, totalEnrollmentRecords - notStarted);
    const enrollmentStatusValues = [startedActive, notStarted, resigned];
    const enrollmentStatusLabels = [
        hasCourse ? 'Started / Active' : 'Started Enrollments',
        'Not Started',
        'Resigned / Suspended'
    ];
    const enrollmentStatusColors = ['#10b981', '#ef4444', '#f97316'];
    const enrollmentStatusHasValues = enrollmentStatusValues.some(function(value) {
        return Number(value) > 0;
    });
    const enrollmentStatusTotal = enrollmentStatusValues.reduce(function(sum, value) {
        return sum + Number(value || 0);
    }, 0);

    if (enrollmentStatusEmpty) {
        enrollmentStatusEmpty.style.display = enrollmentStatusHasValues ? 'none' : 'block';
    }

    if (enrollmentStatusCanvas && enrollmentStatusHasValues) {
        kpiEnrollmentStatusPieChartInstance = new Chart(enrollmentStatusCanvas.getContext('2d'), {
            type: 'pie',
            plugins: [piePercentLabelsPlugin],
            data: {
                labels: enrollmentStatusLabels,
                datasets: [{
                    data: enrollmentStatusValues,
                    backgroundColor: enrollmentStatusColors,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: kpiPieLegendOptions,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = Number(context.parsed || 0);
                                const percent = enrollmentStatusTotal > 0 ? ((value / enrollmentStatusTotal) * 100).toFixed(1) : '0.0';
                                return `${context.label}: ${numberFmt.format(value)} (${percent}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
}

function setFeedbackHidden(id, hidden) {
    const element = document.getElementById(id);
    if (element) {
        element.style.display = hidden ? 'none' : '';
    }
}

function clearFeedbackAnalyzerText() {
    const quantWrap = document.getElementById('feedbackQuantBars');
    const keywordWrap = document.getElementById('feedbackKeywords');
    const flaggedWrap = document.getElementById('feedbackFlaggedComments');
    if (quantWrap) {
        quantWrap.innerHTML = '';
    }
    if (keywordWrap) {
        keywordWrap.innerHTML = '';
    }
    if (flaggedWrap) {
        flaggedWrap.innerHTML = '';
    }
}

function resetFeedbackAnalyzer(metaText) {
    const overall = document.getElementById('feedbackOverallAverage');
    const meta = document.getElementById('feedbackAnalyzerMeta');
    const badge = document.getElementById('feedbackAnalyzerBadge');

    clearFeedbackAnalyzerText();
    if (overall) {
        overall.textContent = 'Overall: --/5';
    }
    if (meta) {
        meta.textContent = metaText || 'Select a course to load course-wide quantitative feedback and AI sentiment insights.';
    }
    if (badge) {
        badge.textContent = feedbackInsightsState.loading ? 'Analyzing…' : 'Healthcare QA';
    }

    setFeedbackHidden('feedbackQuantEmpty', false);
    setFeedbackHidden('feedbackKeywordsEmpty', false);
    setFeedbackHidden('feedbackFlaggedEmpty', false);
    setFeedbackHidden('feedbackSentimentEmpty', false);

    if (feedbackSentimentChartInstance) {
        feedbackSentimentChartInstance.destroy();
        feedbackSentimentChartInstance = null;
    }
}

function getFeedbackBarTone(avgScore) {
    if (avgScore > 4) {
        return 'is-good';
    }
    if (avgScore >= 3) {
        return 'is-mid';
    }
    return 'is-low';
}

function renderFeedbackSentimentChart(sentiment) {
    const canvas = document.getElementById('feedbackSentimentChart');
    if (!canvas) {
        return;
    }

    const split = sentiment && sentiment.sentiment_split ? sentiment.sentiment_split : null;
    const values = split ? [
        Number(split.positive_pct || 0),
        Number(split.neutral_pct || 0),
        Number(split.negative_pct || 0)
    ] : [];
    const hasData = values.some(function(value) {
        return value > 0;
    });

    if (feedbackSentimentChartInstance) {
        feedbackSentimentChartInstance.destroy();
        feedbackSentimentChartInstance = null;
    }

    setFeedbackHidden('feedbackSentimentEmpty', hasData);
    if (!hasData) {
        return;
    }

    const legendColor = getThemeCssVar('--ad-feedback-label', '#16325c');
    const ringBorderColor = getThemeCssVar('--ad-feedback-panel-bg', '#ffffff');

    feedbackSentimentChartInstance = new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Positive', 'Neutral', 'Negative'],
            datasets: [{
                data: values,
                backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                borderColor: ringBorderColor,
                borderWidth: 6,
                hoverOffset: 6
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: legendColor,
                        usePointStyle: true,
                        padding: 18
                    }
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            return context.label + ': ' + context.parsed + '%';
                        }
                    }
                }
            }
        }
    });
}

function renderFeedbackQuantitative(quantitative) {
    const wrap = document.getElementById('feedbackQuantBars');
    const overall = document.getElementById('feedbackOverallAverage');
    if (!wrap || !overall) {
        return;
    }

    wrap.innerHTML = '';
    const questions = quantitative && quantitative.questions ? Object.values(quantitative.questions) : [];
    if (!questions.length) {
        overall.textContent = 'Overall: --/5';
        setFeedbackHidden('feedbackQuantEmpty', false);
        return;
    }

    setFeedbackHidden('feedbackQuantEmpty', true);
    overall.textContent = 'Overall: ' + Number(quantitative.overall_average || 0).toFixed(2) + '/5';

    questions.forEach(function(item) {
        const avg = Number(item.avg_score || 0);
        const max = Number(item.max_score || 5);
        const pct = Math.max(0, Math.min(100, (avg / max) * 100));
        const tone = getFeedbackBarTone(avg);

        const row = document.createElement('div');

        const label = document.createElement('div');
        label.className = 'admindash-feedback__metric-label';

        const question = document.createElement('span');
        question.textContent = String(item.question || 'Question');

        const score = document.createElement('span');
        score.textContent = avg.toFixed(2) + '/' + max.toFixed(0);

        label.appendChild(question);
        label.appendChild(score);

        const meta = document.createElement('div');
        meta.className = 'admindash-feedback__metric-meta';
        meta.textContent = 'Responses: ' + numberFmt.format(Number(item.response_count || 0));

        const progress = document.createElement('div');
        progress.className = 'admindash-feedback__progress';

        const bar = document.createElement('div');
        bar.className = 'admindash-feedback__progress-bar ' + tone;
        bar.style.width = pct.toFixed(1) + '%';

        progress.appendChild(bar);
        row.appendChild(label);
        row.appendChild(meta);
        row.appendChild(progress);
        wrap.appendChild(row);
    });
}

function renderFeedbackKeywords(sentiment) {
    const wrap = document.getElementById('feedbackKeywords');
    if (!wrap) {
        return;
    }

    wrap.innerHTML = '';
    const keywords = sentiment && Array.isArray(sentiment.trending_keywords) ? sentiment.trending_keywords : [];
    setFeedbackHidden('feedbackKeywordsEmpty', keywords.length > 0);

    keywords.forEach(function(keyword) {
        const chip = document.createElement('span');
        chip.className = 'admindash-feedback__chip';
        chip.textContent = String(keyword || '');
        wrap.appendChild(chip);
    });
}

function renderFeedbackFlaggedComments(sentiment) {
    const wrap = document.getElementById('feedbackFlaggedComments');
    if (!wrap) {
        return;
    }

    wrap.innerHTML = '';
    const comments = sentiment && Array.isArray(sentiment.flagged_comments) ? sentiment.flagged_comments : [];
    setFeedbackHidden('feedbackFlaggedEmpty', comments.length > 0);

    comments.forEach(function(item, index) {
        const card = document.createElement('article');
        card.className = 'admindash-feedback__comment';

        const head = document.createElement('div');
        head.className = 'admindash-feedback__comment-head';

        const title = document.createElement('strong');
        title.textContent = 'Comment ' + (index + 1);

        const badge = document.createElement('span');
        const sentimentValue = String(item.sentiment || 'neutral').toLowerCase();
        badge.className = 'admindash-feedback__sentiment is-' + sentimentValue;
        badge.textContent = sentimentValue;

        head.appendChild(title);
        head.appendChild(badge);

        const text = document.createElement('p');
        text.className = 'admindash-feedback__comment-text';
        text.textContent = String(item.text || '');

        card.appendChild(head);
        card.appendChild(text);
        wrap.appendChild(card);
    });
}

function renderFeedbackInsights(payload, courseid) {
    const meta = document.getElementById('feedbackAnalyzerMeta');
    const badge = document.getElementById('feedbackAnalyzerBadge');
    const sentiment = payload && payload.sentiment ? payload.sentiment : {};
    const quantitative = payload && payload.quantitative ? payload.quantitative : {};
    const insightMeta = payload && payload.meta ? payload.meta : {};
    const commentsCount = Number(insightMeta.comments_count || 0);
    const hasQuantitative = Boolean(insightMeta.has_quantitative);
    const hasComments = Boolean(insightMeta.has_comments);
    const error = String(sentiment.error || '').trim();

    if (badge) {
        badge.textContent = commentsCount > 0 ? (numberFmt.format(commentsCount) + ' Comments') : 'Healthcare QA';
    }
    if (meta) {
        if (error !== '') {
            meta.textContent = 'Quantitative feedback loaded, but AI sentiment analysis is currently unavailable.';
        } else if (!hasQuantitative && !hasComments) {
            meta.textContent = 'No feedback submissions found for this course yet.';
        } else {
            meta.textContent = 'Course-wide learner feedback insights based on Moodle feedback responses.';
        }
    }

    renderFeedbackSentimentChart(sentiment);
    renderFeedbackQuantitative(quantitative);
    renderFeedbackKeywords(sentiment);
    renderFeedbackFlaggedComments(sentiment);

    if (error !== '' && !hasComments) {
        setFeedbackHidden('feedbackSentimentEmpty', false);
    }
}

async function loadFeedbackInsights(courseid) {
    const courseIdNumber = Number(courseid || 0);
    if (courseIdNumber <= 0) {
        feedbackInsightsState = { loading: false, lastCourseId: 0, requestId: feedbackInsightsState.requestId };
        resetFeedbackAnalyzer('Select a course to load course-wide quantitative feedback and AI sentiment insights.');
        return;
    }

    feedbackInsightsState.loading = true;
    feedbackInsightsState.lastCourseId = courseIdNumber;
    feedbackInsightsState.requestId += 1;
    const requestId = feedbackInsightsState.requestId;
    resetFeedbackAnalyzer('Loading feedback insights and analyzing comments with Groq…');

    const url = new URL('data.php', window.location.href);
    url.searchParams.set('mode', 'feedback_insights');
    url.searchParams.set('courseid', String(courseIdNumber));
    url.searchParams.set('t', String(Date.now()));

    try {
        const response = await fetch(url.toString(), {
            credentials: 'same-origin',
            cache: 'no-store'
        });
        const payload = await response.json().catch(function() {
            return {};
        });
        if (!response.ok) {
            throw new Error((payload && payload.error) ? payload.error : ('HTTP ' + response.status));
        }
        if (requestId !== feedbackInsightsState.requestId) {
            return;
        }
        renderFeedbackInsights(payload || {}, courseIdNumber);
    } catch (error) {
        if (requestId !== feedbackInsightsState.requestId) {
            return;
        }
        resetFeedbackAnalyzer('Feedback insights could not be loaded right now.');
    } finally {
        if (requestId === feedbackInsightsState.requestId) {
            feedbackInsightsState.loading = false;
        }
    }
}

function getModalInstance() {
    if (kpiUsersModal) {
        return kpiUsersModal;
    }
    const element = document.getElementById('kpiUsersModal');
    if (!element) {
        return null;
    }

    const closeBtn = element.querySelector('[data-admindash-close-modal="1"]');
    const searchInput = element.querySelector('#kpiUsersSearch');
    const closeModal = function() {
        element.classList.remove('is-open');
        element.classList.remove('show');
        element.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('admindash-modal-open');
        if (lastModalFocus && typeof lastModalFocus.focus === 'function') {
            lastModalFocus.focus();
        }
    };

    const showModal = function() {
        lastModalFocus = document.activeElement;
        document.body.classList.remove('admindash-sidebar-open');
        element.classList.add('is-open');
        element.classList.add('show');
        element.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admindash-modal-open');
        if (closeBtn) {
            closeBtn.focus();
        }
    };

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    if (searchInput && !searchInput.dataset.bound) {
        searchInput.addEventListener('input', function() {
            applyKpiModalFilter();
        });
        searchInput.dataset.bound = '1';
    }
    element.addEventListener('click', function(event) {
        if (event.target === element) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && element.classList.contains('is-open')) {
            closeModal();
        }
    });

    kpiUsersModal = {
        show: showModal,
        hide: closeModal,
        element: element
    };
    return kpiUsersModal;
}

function getAtRiskModalInstance() {
    if (atRiskDetailsModal) {
        return atRiskDetailsModal;
    }
    const element = document.getElementById('atRiskDetailsModal');
    if (!element) {
        return null;
    }

    const closeBtn = element.querySelector('[data-admindash-close-risk-modal="1"]');
    const closeModal = function() {
        element.classList.remove('is-open');
        element.classList.remove('show');
        element.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('admindash-modal-open');
        if (lastModalFocus && typeof lastModalFocus.focus === 'function') {
            lastModalFocus.focus();
        }
    };

    const showModal = function() {
        lastModalFocus = document.activeElement;
        document.body.classList.remove('admindash-sidebar-open');
        element.classList.add('is-open');
        element.classList.add('show');
        element.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admindash-modal-open');
        if (closeBtn) {
            closeBtn.focus();
        }
    };

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    element.addEventListener('click', function(event) {
        if (event.target === element) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && element.classList.contains('is-open')) {
            closeModal();
        }
    });

    atRiskDetailsModal = {
        show: showModal,
        hide: closeModal,
        element: element
    };
    return atRiskDetailsModal;
}

function renderKpiModalTableRows(users) {
    const tbody = document.getElementById('kpiUsersTableBody');
    const thEnrol = document.getElementById('kpiUsersThEnrol');
    if (!tbody) {
        return;
    }
    const enrolMode = kpiModalShowsCourseEnrolColumn();
    if (thEnrol) {
        thEnrol.style.display = enrolMode ? '' : 'none';
    }

    tbody.innerHTML = users.map(function(user) {
        let enrolCell = '';
        if (enrolMode) {
            const cn = user.course_name ? String(user.course_name) : '';
            const en = user.enrolment_label ? String(user.enrolment_label) : '';
            if (cn && en) {
                enrolCell = ''
                    + '<td class="admindash-kpi-enrol-cell">'
                    + '<div class="admindash-kpi-enrol-cell__course">' + escapeHtml(cn) + '</div>'
                    + '<div class="admindash-kpi-enrol-cell__meta">' + escapeHtml(en) + '</div>'
                    + '</td>';
            } else if (en) {
                enrolCell = '<td class="admindash-kpi-enrol-cell">' + escapeHtml(en) + '</td>';
            } else if (cn) {
                enrolCell = '<td class="admindash-kpi-enrol-cell">' + escapeHtml(cn) + '</td>';
            } else {
                enrolCell = '<td class="admindash-kpi-enrol-cell text-muted">—</td>';
            }
        }
        return ''
            + '<tr>'
            + '<td>' + escapeHtml(user.name || '') + '</td>'
            + '<td>' + escapeHtml(user.department || '-') + '</td>'
            + '<td>' + escapeHtml(user.clinicname || '-') + '</td>'
            + (enrolMode ? enrolCell : '')
            + '</tr>';
    }).join('');
}

function updateKpiModalSearchMeta(visibleCount, totalCount, query) {
    const searchMeta = document.getElementById('kpiUsersSearchMeta');
    if (!searchMeta) {
        return;
    }
    if (!totalCount) {
        searchMeta.textContent = '';
        return;
    }
    const noun = kpiModalMetricKey === 'certified'
        ? 'certificate issues'
        : (kpiModalMetricKey === 'total_enrollments' ? 'records' : 'users');
    if (query) {
        searchMeta.textContent = 'Showing ' + visibleCount + ' of ' + totalCount + ' ' + noun;
        return;
    }
    searchMeta.textContent = totalCount + ' ' + noun + ' loaded';
}

function applyKpiModalFilter() {
    const tbody = document.getElementById('kpiUsersTableBody');
    const emptyEl = document.getElementById('kpiUsersModalEmpty');
    const searchInput = document.getElementById('kpiUsersSearch');
    if (!tbody) {
        return;
    }

    const query = String(searchInput ? searchInput.value : '').trim().toLowerCase();
    const filteredUsers = !query ? kpiModalUsers : kpiModalUsers.filter(function(user) {
        const haystack = [
            user.name,
            user.department,
            user.clinicname,
            user.course_name,
            user.enrolment_label
        ].join(' ').toLowerCase();
        return haystack.indexOf(query) !== -1;
    });

    if (!filteredUsers.length) {
        tbody.innerHTML = '';
        if (emptyEl) {
            emptyEl.textContent = query ? 'No users match your search.' : 'No users found for the selected KPI.';
            emptyEl.style.display = 'block';
        }
        updateKpiModalSearchMeta(0, kpiModalUsers.length, query);
        return;
    }

    if (emptyEl) {
        emptyEl.style.display = 'none';
        emptyEl.textContent = 'No users found for the selected KPI.';
    }
    renderKpiModalTableRows(filteredUsers);
    updateKpiModalSearchMeta(filteredUsers.length, kpiModalUsers.length, query);
}

function setKpiModalLoading(title, subtitle) {
    const titleEl = document.getElementById('kpiUsersModalLabel');
    const metaEl = document.getElementById('kpiUsersModalMeta');
    const emptyEl = document.getElementById('kpiUsersModalEmpty');
    const tbody = document.getElementById('kpiUsersTableBody');
    const searchInput = document.getElementById('kpiUsersSearch');
    const searchMeta = document.getElementById('kpiUsersSearchMeta');
    kpiModalUsers = [];
    if (titleEl) {
        titleEl.textContent = title;
    }
    if (metaEl) {
        metaEl.textContent = subtitle || 'Loading…';
    }
    const thEnrol = document.getElementById('kpiUsersThEnrol');
    if (thEnrol) {
        thEnrol.style.display = kpiModalShowsCourseEnrolColumn() ? '' : 'none';
    }
    if (emptyEl) {
        emptyEl.textContent = 'No users found for the selected KPI.';
        emptyEl.style.display = 'none';
    }
    if (searchInput) {
        searchInput.value = '';
        searchInput.disabled = true;
    }
    if (searchMeta) {
        searchMeta.textContent = '';
    }
    if (tbody) {
        const colSpan = kpiModalShowsCourseEnrolColumn() ? 4 : 3;
        tbody.innerHTML = ''
            + '<tr>'
            + '<td colspan="' + colSpan + '" class="text-center py-4">'
            + '<div class="spinner-border text-primary" role="status" aria-hidden="true"></div>'
            + '<div class="mt-2 text-muted small">Loading users…</div>'
            + '</td>'
            + '</tr>';
    }
    setReminderButtonState({metric: '', count: 0, label: title, loading: false, hidden: true, statusText: ''});
}

function renderKpiModalRows(users, metaText) {
    const metaEl = document.getElementById('kpiUsersModalMeta');
    const emptyEl = document.getElementById('kpiUsersModalEmpty');
    const searchInput = document.getElementById('kpiUsersSearch');
    if (metaEl) {
        metaEl.textContent = metaText;
    }
    kpiModalUsers = Array.isArray(users) ? users.slice() : [];
    if (searchInput) {
        searchInput.disabled = !kpiModalUsers.length;
    }
    if (!users.length) {
        const tbody = document.getElementById('kpiUsersTableBody');
        if (tbody) {
            tbody.innerHTML = '';
        }
        if (emptyEl) {
            emptyEl.textContent = 'No users found for the selected KPI.';
            emptyEl.style.display = 'block';
        }
        updateKpiModalSearchMeta(0, 0, '');
        return;
    }
    applyKpiModalFilter();
}

function getSelectedOptionText(selectId, fallbackText) {
    const select = document.getElementById(selectId);
    if (!select || !select.selectedOptions || !select.selectedOptions.length) {
        return fallbackText || '';
    }
    return String(select.selectedOptions[0].textContent || fallbackText || '').trim();
}

function formatTimerDuration(totalSeconds) {
    const safeSeconds = Math.max(0, Number(totalSeconds || 0));
    const minutes = Math.floor(safeSeconds / 60);
    const seconds = safeSeconds % 60;
    return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
}

// ─── Upcoming-event countdown ─────────────────────────────────────────────
let upcomingEvent = { type: '', label: '', meta: '', target_ts: 0, date_formatted: '', course_name: '', tickId: null };

function formatUpcomingCountdown(totalSeconds) {
    const s = Math.max(0, Math.floor(Number(totalSeconds || 0)));
    if (s <= 0) { return 'Starting now'; }
    const days  = Math.floor(s / 86400);
    const hours = Math.floor((s % 86400) / 3600);
    const mins  = Math.floor((s % 3600) / 60);
    const secs  = s % 60;
    const hh = String(hours).padStart(2, '0');
    const mm = String(mins).padStart(2, '0');
    const ss = String(secs).padStart(2, '0');
    return days > 0 ? `${days}d ${hh}:${mm}:${ss}` : `${hh}:${mm}:${ss}`;
}

function renderUpcomingTick() {
    const countdownEl = document.getElementById('admindashTimerCountdown');
    if (!countdownEl) { return; }
    if (!upcomingEvent.target_ts) {
        countdownEl.textContent = '- -';
        return;
    }
    const remaining = (upcomingEvent.target_ts * 1000 - Date.now()) / 1000;
    countdownEl.textContent = formatUpcomingCountdown(remaining);
}

function startUpcomingTick() {
    if (upcomingEvent.tickId) { window.clearInterval(upcomingEvent.tickId); }
    renderUpcomingTick();
    upcomingEvent.tickId = window.setInterval(renderUpcomingTick, 1000);
}

function applyUpcomingEventToCard(ev) {
    upcomingEvent.type           = ev.type          || '';
    upcomingEvent.label          = ev.label         || '';
    upcomingEvent.meta           = ev.meta          || '';
    upcomingEvent.target_ts      = Number(ev.target_ts || 0);
    upcomingEvent.date_formatted = ev.date_formatted || '';
    upcomingEvent.course_name    = (ev.course_name && String(ev.course_name).trim()) || '';

    const eyebrow  = document.getElementById('admindashTimerEyebrow');
    const title    = document.getElementById('admindashTimerTitle');
    const courseEl = document.getElementById('admindashTimerCourse');
    const metaEl   = document.getElementById('admindashTimerMeta');
    const modeEl   = document.getElementById('admindashTimerMode');
    const scopeEl  = document.getElementById('admindashTimerScope');
    const scopeLabel = document.getElementById('admindashTimerScopeLabel');
    const card     = document.getElementById('admindashTimerCard');

    const isQuiz   = ev.type === 'quiz';
    const isCourse = ev.type === 'course';
    const isNone   = ev.type === 'none' || !ev.type;
    const courseName = upcomingEvent.course_name;

    if (eyebrow) {
        eyebrow.textContent = isQuiz ? 'Upcoming Test' : isCourse ? 'Upcoming Course' : 'Nothing Upcoming';
    }
    if (title) {
        title.textContent = ev.label || (isQuiz ? 'No scheduled tests' : 'No upcoming courses');
    }
    if (courseEl) {
        if (isQuiz && courseName) {
            courseEl.removeAttribute('hidden');
            courseEl.textContent = courseName;
        } else {
            courseEl.setAttribute('hidden', '');
            courseEl.textContent = '';
        }
    }
    if (metaEl) {
        metaEl.textContent = ev.date_formatted
            ? (isQuiz ? 'Opens: ' : 'Starts: ') + ev.date_formatted
            : (ev.meta || '');
    }
    if (modeEl) {
        modeEl.textContent = isQuiz ? 'Quiz' : isCourse ? 'New Course' : '—';
    }
    if (scopeLabel) {
        scopeLabel.textContent = isCourse ? 'Starts' : 'On';
    }
    if (scopeEl) {
        scopeEl.textContent = isNone ? '—' : (ev.date_formatted || '—');
    }
    if (card) {
        card.setAttribute('data-timer-state', isNone ? 'none' : (ev.type || 'active'));
    }

    startUpcomingTick();
}

async function loadUpcomingEvent() {
    const courseId = String(document.getElementById('courseSelect')?.value || '0');
    const url = new URL('data.php', window.location.href);
    url.searchParams.set('mode', 'upcoming_event');
    url.searchParams.set('courseid', courseId);
    url.searchParams.set('_', String(Date.now()));
    try {
        const res  = await fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store' });
        const data = await res.json().catch(function() { return {}; });
        applyUpcomingEventToCard(data);
    } catch (_) {
        applyUpcomingEventToCard({ type: 'none', label: 'Unavailable', meta: '', target_ts: 0, date_formatted: '', course_name: '' });
    }
}
// ─── End upcoming-event countdown ─────────────────────────────────────────

// Live-feed background refresh timer (internal only — no longer drives the card DOM).
function renderDashboardTimer() {
    // Kept for live-feed interval bookkeeping; card DOM is now managed by loadUpcomingEvent().
}

function resetDashboardTimerCycle() {
    liveFeedState.nextRefreshAt = Date.now() + liveFeedState.refreshEveryMs;
}

function setupDashboardTimer() {
    if (liveFeedState.countdownIntervalId) {
        window.clearInterval(liveFeedState.countdownIntervalId);
        liveFeedState.countdownIntervalId = null;
    }
}

function isReminderMetric(metric) {
    return metric === 'failed' || metric === 'not_attempted' || metric === 'notattempted' || metric === 'dropped_midway';
}

function setReminderButtonState(state) {
    const button = document.getElementById('kpiReminderButton');
    const status = document.getElementById('kpiReminderStatus');
    if (!button || !status) {
        return;
    }

    kpiModalState = {
        metric: String(state.metric || ''),
        label: String(state.label || ''),
        count: Number(state.count || 0),
        loading: state.loading === true
    };

    const hidden = state.hidden === true || !isReminderMetric(kpiModalState.metric);
    button.style.display = hidden ? 'none' : '';
    button.disabled = hidden || kpiModalState.loading || kpiModalState.count < 1;
    button.textContent = kpiModalState.loading ? 'Sending…' : 'Send Reminder to All';
    status.textContent = String(state.statusText || '');
}

function buildReminderStatusText(metric, count, label) {
    if (!isReminderMetric(metric) || count < 1) {
        return '';
    }
    const noun = count === 1 ? 'user' : 'users';
    return `Send a Moodle reminder to ${count} ${noun} in ${label}.`;
}

function sendKpiReminders() {
    if (!isReminderMetric(kpiModalState.metric) || kpiModalState.count < 1 || kpiModalState.loading) {
        return;
    }

    const buttonLabel = kpiModalState.label || 'Selected KPI';
    setReminderButtonState({
        metric: kpiModalState.metric,
        label: buttonLabel,
        count: kpiModalState.count,
        loading: true,
        statusText: 'Sending Moodle reminders…'
    });

    const body = new URLSearchParams();
    body.set('sesskey', adminDashSesskey);
    body.set('courseid', String(Number(dashboardState.courseid || 0)));
    body.set('department', dashboardState.department || '');
    body.set('moduleid', String(Number(dashboardState.moduleid || 0)));
    body.set('metric', String(kpiModalState.metric || ''));

    fetch(new URL('send_kpi_reminders.php', window.location.href).toString(), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
    })
        .then(function(response) {
            return response.json().catch(function() {
                return {};
            }).then(function(payload) {
                if (!response.ok) {
                    throw new Error((payload && payload.error) ? payload.error : ('HTTP ' + response.status));
                }
                return payload;
            });
        })
        .then(function(payload) {
            const sent = Number(payload && payload.sentcount || 0);
            const recipients = Number(payload && payload.recipientcount || 0);
            const statusText = sent === recipients
                ? `Reminder sent to ${sent} ${sent === 1 ? 'user' : 'users'}.`
                : `Reminder sent to ${sent} of ${recipients} users.`;

            setReminderButtonState({
                metric: kpiModalState.metric,
                label: buttonLabel,
                count: kpiModalState.count,
                loading: false,
                statusText: statusText
            });

            Notification.alert('Reminders sent', statusText);
            return null;
        })
        .catch(function(error) {
            setReminderButtonState({
                metric: kpiModalState.metric,
                label: buttonLabel,
                count: kpiModalState.count,
                loading: false,
                statusText: 'Failed to send reminders.'
            });
            Notification.exception(error);
        });
}

function setAtRiskReminderState(state) {
    const button = document.getElementById('atRiskReminderButton');
    const status = document.getElementById('atRiskReminderStatus');
    if (!button || !status) {
        return;
    }

    atRiskState.loading = state.loading === true;
    const count = Array.isArray(atRiskState.rows) ? atRiskState.rows.length : 0;
    const canSend = atRiskState.courseRunning && count > 0;

    button.style.display = canSend ? '' : 'none';
    button.disabled = !canSend || atRiskState.loading;
    button.textContent = atRiskState.loading ? 'Sending…' : 'Send Reminder to At-Risk';
    status.textContent = String(state.statusText || '');
}

function openAtRiskDetails(userId) {
    const modal = getAtRiskModalInstance();
    if (!modal) {
        Notification.alert('Modal unavailable', 'Risk details modal could not be initialised.');
        return;
    }

    const row = (atRiskState.rows || []).find(function(item) {
        return Number(item.userid || 0) === Number(userId || 0);
    });
    if (!row) {
        return;
    }

    const titleEl = document.getElementById('atRiskDetailsModalLabel');
    const metaEl = document.getElementById('atRiskDetailsMeta');
    const bodyEl = document.getElementById('atRiskDetailsBody');
    if (titleEl) {
        titleEl.textContent = row.name || 'At-Risk Details';
    }
    if (metaEl) {
        metaEl.textContent = `${row.coursefullname || 'Course'} | Risk score ${row.risk_score || 0}/3`;
    }
    if (bodyEl) {
        const reasons = Array.isArray(row.reasons) ? row.reasons : [];
        const pretestText = row.pretest_pct === null || row.pretest_avg_pct === null
            ? '-'
            : `${row.pretest_pct}% (cohort ${row.pretest_avg_pct}%)`;
        const deadlineText = row.deadline_at ? new Date(Number(row.deadline_at) * 1000).toLocaleString() : '-';
        bodyEl.innerHTML = ''
            + '<div class="admindash-risk-detail-grid">'
            + '<div class="admindash-risk-detail-card"><div class="admindash-risk-detail-label">Department</div><div class="admindash-risk-detail-value">' + escapeHtml(row.department || '-') + '</div></div>'
            + '<div class="admindash-risk-detail-card"><div class="admindash-risk-detail-label">Days Since Login</div><div class="admindash-risk-detail-value">' + escapeHtml(String(row.days_since_login ?? '-')) + '</div></div>'
            + '<div class="admindash-risk-detail-card"><div class="admindash-risk-detail-label">Completion</div><div class="admindash-risk-detail-value">' + escapeHtml(String(row.completion_pct ?? 0)) + '%</div></div>'
            + '<div class="admindash-risk-detail-card"><div class="admindash-risk-detail-label">Pre-Test</div><div class="admindash-risk-detail-value">' + escapeHtml(pretestText) + '</div></div>'
            + '<div class="admindash-risk-detail-card"><div class="admindash-risk-detail-label">Deadline</div><div class="admindash-risk-detail-value">' + escapeHtml(deadlineText) + '</div></div>'
            + '</div>'
            + '<div class="admindash-risk-detail-section">'
            + '<div class="admindash-risk-detail-heading">Risk Signals</div>'
            + (reasons.length
                ? '<ul class="admindash-risk-detail-list">' + reasons.map(function(reason) { return '<li>' + escapeHtml(reason) + '</li>'; }).join('') + '</ul>'
                : '<div class="text-muted small">No additional risk detail available.</div>')
            + '</div>';
    }

    modal.show();
}

function sendAtRiskReminders() {
    const rows = Array.isArray(atRiskState.rows) ? atRiskState.rows : [];
    if (!atRiskState.courseRunning || !rows.length || atRiskState.loading) {
        return;
    }

    setAtRiskReminderState({loading: true, statusText: 'Sending at-risk reminders…'});

    const body = new URLSearchParams();
    body.set('sesskey', adminDashSesskey);
    body.set('courseid', String(Number(dashboardState.courseid || 0)));
    body.set('department', dashboardState.department || '');
    body.set('userids', rows.map(function(row) { return Number(row.userid || 0); }).filter(Boolean).join(','));

    fetch(new URL('send_at_risk_reminders.php', window.location.href).toString(), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
    })
        .then(function(response) {
            return response.json().catch(function() {
                return {};
            }).then(function(payload) {
                if (!response.ok) {
                    throw new Error((payload && payload.error) ? payload.error : ('HTTP ' + response.status));
                }
                return payload;
            });
        })
        .then(function(payload) {
            const sent = Number(payload && payload.sentcount || 0);
            const recipients = Number(payload && payload.recipientcount || 0);
            const statusText = sent === recipients
                ? `At-risk reminder sent to ${sent} ${sent === 1 ? 'participant' : 'participants'}.`
                : `At-risk reminder sent to ${sent} of ${recipients} participants.`;
            setAtRiskReminderState({loading: false, statusText: statusText});
            Notification.alert('At-risk reminders sent', statusText);
            return null;
        })
        .catch(function(error) {
            setAtRiskReminderState({loading: false, statusText: 'Failed to send at-risk reminders.'});
            Notification.exception(error);
        });
}

function openKpiUsersModal(metric, label) {
    const modal = getModalInstance();
    if (!modal) {
        Notification.alert('Modal unavailable', 'KPI modal could not be initialised.');
        return;
    }

    kpiModalMetricKey = String(metric || '');

    setKpiModalLoading(label + ' Users', 'Loading…');
    modal.show();

    require(['core/ajax'], function(Ajax) {
        Ajax.call([{
            methodname: 'local_admindashboard_get_kpi_users',
            args: {
                courseid: Number(dashboardState.courseid || 0),
                department: dashboardState.department || '',
                moduleid: Number(dashboardState.moduleid || 0),
                metric: String(metric || '')
            }
        }])[0]
        .then(function(response) {
            const count = Number(response && response.count || 0);
            const isRecordMetric = kpiModalMetricKey === 'total_enrollments' || kpiModalMetricKey === 'certified';
            const metaText = kpiModalMetricKey === 'certified'
                ? (count === 1 ? '1 certificate issue' : (count + ' certificate issues'))
                : (isRecordMetric
                    ? (count === 1 ? '1 enrollment record' : (count + ' enrollment records'))
                    : (count === 1 ? '1 user' : (count + ' users')));
            renderKpiModalRows((response && response.users) ? response.users : [], metaText);
            setReminderButtonState({
                metric: metric,
                label: label,
                count: count,
                loading: false,
                hidden: !isReminderMetric(metric),
                statusText: buildReminderStatusText(metric, count, label)
            });
            return null;
        })
        .catch(function(error) {
            renderKpiModalRows([], 'Failed to load users');
            setReminderButtonState({
                metric: metric,
                label: label,
                count: 0,
                loading: false,
                hidden: !isReminderMetric(metric),
                statusText: 'Unable to prepare reminder recipients.'
            });
            Notification.exception(error);
        });
    });
}

const doughnutValueLabelsPlugin = {
    id: 'admindashDoughnutValueLabels',
    afterDatasetsDraw(chart, args, pluginOptions) {
        const {ctx} = chart;
        const datasetIndex = 0;
        const meta = chart.getDatasetMeta(datasetIndex);
        if (!meta || !meta.data || !meta.data.length) {
            return;
        }

        const data = chart.data?.datasets?.[datasetIndex]?.data || [];
        const total = data.reduce((sum, v) => sum + (Number(v) || 0), 0);
        const minPctToShow = Number(pluginOptions?.minPctToShow ?? 6);
        const fontSize = Number(pluginOptions?.fontSize ?? 12);
        const color = String(pluginOptions?.color ?? '#0b1b3d');

        ctx.save();
        ctx.font = `800 ${fontSize}px system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif`;
        ctx.fillStyle = color;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        meta.data.forEach((arc, i) => {
            const raw = Number(data[i]) || 0;
            if (raw <= 0) {
                return;
            }

            const pct = total > 0 ? (100 * raw / total) : 0;
            if (pct < minPctToShow) {
                return;
            }

            const pos = arc.tooltipPosition();
            ctx.fillText(numberFmt.format(raw), pos.x, pos.y);
        });

        ctx.restore();
    }
};

const barValueLabelsPlugin = {
    id: 'admindashBarValueLabels',
    afterDatasetsDraw(chart, args, pluginOptions) {
        if (!pluginOptions || pluginOptions.enabled !== true) {
            return;
        }

        const datasetIndex = Number(pluginOptions.datasetIndex ?? 0);
        const dataset = chart.data?.datasets?.[datasetIndex];
        const meta = chart.getDatasetMeta(datasetIndex);
        if (!dataset || !meta || !meta.data || !meta.data.length) {
            return;
        }

        const formatter = typeof pluginOptions.formatter === 'function'
            ? pluginOptions.formatter
            : function(value) {
                return `${Number(value) || 0}%`;
            };

        const ctx = chart.ctx;
        const color = String(pluginOptions.color ?? '#0b1b3d');
        const fontSize = Number(pluginOptions.fontSize ?? 11);
        const offset = Number(pluginOptions.offset ?? 8);

        ctx.save();
        ctx.fillStyle = color;
        ctx.font = `800 ${fontSize}px system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';

        meta.data.forEach(function(element, index) {
            const rawValue = Number(dataset.data?.[index] ?? 0);
            if (!Number.isFinite(rawValue) || rawValue <= 0) {
                return;
            }

            const position = element.tooltipPosition();
            const label = formatter(rawValue, index, dataset, chart);
            const y = Math.max(position.y - offset, 12);
            ctx.fillText(String(label), position.x, y);
        });

        ctx.restore();
    }
};

function resetSelectToFirstOption(selectEl) {
    const first = selectEl.options[0] ? { value: selectEl.options[0].value, text: selectEl.options[0].textContent } : null;
    selectEl.innerHTML = '';
    if (first) {
        const opt = document.createElement('option');
        opt.value = first.value;
        opt.textContent = first.text;
        selectEl.appendChild(opt);
    }
}

function populateDeptOptions(departments) {
    const deptSelect = document.getElementById('deptSelect');
    const current = deptSelect.value;
    resetSelectToFirstOption(deptSelect);
    for (const dept of (departments || [])) {
        const opt = document.createElement('option');
        opt.value = dept;
        opt.textContent = dept;
        deptSelect.appendChild(opt);
    }
    if (current && [...deptSelect.options].some(o => o.value === current)) {
        deptSelect.value = current;
    } else {
        deptSelect.value = '';
    }
}

function populateModuleOptionsFlat(modules) {
    const moduleSelect = document.getElementById('moduleSelect');
    const current = moduleSelect.value;
    resetSelectToFirstOption(moduleSelect);
    for (const m of (modules || [])) {
        const opt = document.createElement('option');
        opt.value = String(m.id);
        opt.textContent = m.name;
        moduleSelect.appendChild(opt);
    }
    if (current && [...moduleSelect.options].some(o => o.value === current)) {
        moduleSelect.value = current;
    } else {
        moduleSelect.value = '0';
    }
}

function populateModuleOptionsGrouped(groups) {
    const moduleSelect = document.getElementById('moduleSelect');
    const current = moduleSelect.value;
    resetSelectToFirstOption(moduleSelect);

    for (const group of (groups || [])) {
        const og = document.createElement('optgroup');
        og.label = group.label || 'Module';
        for (const m of (group.items || [])) {
            const opt = document.createElement('option');
            opt.value = String(m.id);
            opt.textContent = m.name;
            og.appendChild(opt);
        }
        moduleSelect.appendChild(og);
    }

    const allOptions = [...moduleSelect.querySelectorAll('option')];
    if (current && allOptions.some(o => o.value === current)) {
        moduleSelect.value = current;
    } else {
        moduleSelect.value = '0';
    }
}

function populateOverviewCourseLeaderSelect(courses) {
    const select = document.getElementById('overviewCourseLeaderSelect');
    const optionsWrap = document.getElementById('overviewCourseLeaderOptions');
    if (!select) {
        return;
    }
    const previous = [...select.selectedOptions].map(option => option.value);
    select.innerHTML = '';
    if (optionsWrap) {
        optionsWrap.innerHTML = '';
    }
    (courses || []).forEach(function(course, index) {
        const value = String(course.id);
        const label = course.fullname || course.shortname || ('Course ' + course.id);
        const selected = previous.length ? previous.includes(value) : false;
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = label;
        opt.selected = selected;
        select.appendChild(opt);

        if (optionsWrap) {
            const item = document.createElement('label');
            item.className = 'admindash-multiselect__option';
            item.innerHTML = '<input type="checkbox" value="' + escapeHtml(value) + '"' + (selected ? ' checked' : '') + '> <span>' + escapeHtml(label) + '</span>';
            optionsWrap.appendChild(item);
        }
    });
    syncOverviewCourseDropdownText();
}

function getOverviewSelectedCourseOptions() {
    const select = document.getElementById('overviewCourseLeaderSelect');
    return select ? [...select.selectedOptions] : [];
}

function syncOverviewCourseDropdownText() {
    const textEl = document.getElementById('overviewCourseLeaderDropdownText');
    const select = document.getElementById('overviewCourseLeaderSelect');
    if (!textEl || !select) {
        return;
    }
    const selected = getOverviewSelectedCourseOptions();
    const total = select.options.length;
    if (!total || selected.length === 0) {
        textEl.textContent = 'All active courses';
    } else if (selected.length === 1) {
        textEl.textContent = selected[0].textContent || '1 course selected';
    } else if (selected.length === total) {
        textEl.textContent = 'All courses selected';
    } else {
        textEl.textContent = selected.length + ' courses selected';
    }
}

function setOverviewCourseSelection(values) {
    const select = document.getElementById('overviewCourseLeaderSelect');
    const optionsWrap = document.getElementById('overviewCourseLeaderOptions');
    const selectedValues = new Set((values || []).map(String));
    if (select) {
        [...select.options].forEach(function(option) {
            option.selected = selectedValues.has(option.value);
        });
    }
    if (optionsWrap) {
        optionsWrap.querySelectorAll('input[type="checkbox"]').forEach(function(input) {
            input.checked = selectedValues.has(input.value);
        });
    }
    syncOverviewCourseDropdownText();
}

function setupOverviewCourseDropdown() {
    const picker = document.getElementById('overviewCourseLeaderPicker');
    const button = document.getElementById('overviewCourseLeaderDropdownBtn');
    const menu = document.getElementById('overviewCourseLeaderMenu');
    const optionsWrap = document.getElementById('overviewCourseLeaderOptions');
    const select = document.getElementById('overviewCourseLeaderSelect');
    if (!picker || !button || !menu || !select) {
        return;
    }
    const close = function() {
        menu.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        picker.classList.remove('is-open');
    };
    const open = function() {
        menu.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        picker.classList.add('is-open');
    };
    button.addEventListener('click', function(event) {
        event.stopPropagation();
        if (menu.hidden) {
            open();
        } else {
            close();
        }
    });
    document.addEventListener('click', function(event) {
        if (!picker.contains(event.target)) {
            close();
        }
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            close();
        }
    });
    if (optionsWrap) {
        optionsWrap.addEventListener('change', function(event) {
            const input = event.target.closest('input[type="checkbox"]');
            if (!input) {
                return;
            }
            const selected = [...optionsWrap.querySelectorAll('input[type="checkbox"]:checked')].map(function(checkbox) {
                return checkbox.value;
            });
            setOverviewCourseSelection(selected);
            select.dispatchEvent(new Event('change'));
        });
    }
    menu.querySelectorAll('[data-overview-course-action]').forEach(function(actionButton) {
        actionButton.addEventListener('click', function() {
            const action = actionButton.getAttribute('data-overview-course-action');
            const values = action === 'all' ? [...select.options].map(function(option) { return option.value; }) : [];
            setOverviewCourseSelection(values);
            select.dispatchEvent(new Event('change'));
        });
    });
}

async function loadMeta(courseid) {
    const url = new URL('data.php', window.location.href);
    url.searchParams.set('mode', 'meta');
    url.searchParams.set('courseid', String(courseid || 0));
    const res = await fetch(url.toString());
    return await res.json();
}

async function loadInitialMeta() {
    const meta = await loadMeta(0);

    const courseSelect = document.getElementById('courseSelect');
    for (const course of (meta.courses || [])) {
        const opt = document.createElement('option');
        opt.value = String(course.id);
        opt.textContent = course.fullname;
        courseSelect.appendChild(opt);
    }
    populateOverviewCourseLeaderSelect(meta.courses || []);

    populateDeptOptions(meta.departments || []);
    if (meta.modulegroups && meta.modulegroups.length) {
        populateModuleOptionsGrouped(meta.modulegroups);
    } else {
        populateModuleOptionsFlat(meta.modules || []);
    }
}

async function reloadDependentMeta() {
    const courseid = document.getElementById('courseSelect').value;
    const meta = await loadMeta(courseid);
    populateDeptOptions(meta.departments || []);
    if (meta.modulegroups && meta.modulegroups.length) {
        populateModuleOptionsGrouped(meta.modulegroups);
    } else {
        populateModuleOptionsFlat(meta.modules || []);
    }
}

async function loadDashboardData() {
    const courseid = document.getElementById('courseSelect').value;
    const department = document.getElementById('deptSelect').value;
    const moduleid = document.getElementById('moduleSelect').value;
    const requestedBarMetric = document.getElementById('barMetricSelect').value;

    const url = new URL('data.php', window.location.href);
    url.searchParams.set('courseid', courseid);
    url.searchParams.set('department', department);
    url.searchParams.set('moduleid', moduleid);

    let data;
    try {
        const res = await fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store' });
        const raw = await res.text();
        if (!res.ok) {
            console.error('[admindash] metrics HTTP', res.status, raw.slice(0, 800));
            Notification.alert('Dashboard', 'Could not load metrics (HTTP ' + res.status + ').');
            return;
        }
        try {
            data = JSON.parse(raw);
        } catch (parseErr) {
            console.error('[admindash] metrics JSON', parseErr, raw.slice(0, 800));
            Notification.alert('Dashboard', 'The server returned an invalid response while loading metrics.');
            return;
        }
    } catch (fetchErr) {
        console.error('[admindash] metrics fetch', fetchErr);
        Notification.alert('Dashboard', 'Could not reach the dashboard data endpoint.');
        return;
    }
    if (!data || typeof data !== 'object') {
        Notification.alert('Dashboard', 'Empty metrics response.');
        return;
    }
    if (data.error) {
        Notification.alert('Dashboard', String(data.message || data.error || 'Metrics failed'));
        return;
    }
    dashboardState = {
        courseid: Number(courseid || 0),
        department: department || '',
        moduleid: Number(moduleid || 0),
        isQuiz: String(data.selected_modname || '') === 'quiz'
    };

    const isQuiz = String(data.selected_modname || '') === 'quiz';
    const attemptedCard = document.getElementById('kpiAttemptedCard');
    attemptedCard.style.display = isQuiz ? '' : 'none';

    const certifiedCard = document.getElementById('kpiCertifiedCard');
    certifiedCard.style.display = '';

    const isCourseOverview = Number(courseid || 0) <= 0;
    document.getElementById('kpiParticipantsLabel').textContent = isCourseOverview ? 'Unique Learners' : 'Total Enrollment';
    document.getElementById('kpiTotalEnrollmentsLabel').textContent = isCourseOverview ? 'Course Enrollments' : 'Current Total Enrollment';
    document.getElementById('kpiPassedLabel').textContent = 'Passed';
    document.getElementById('kpiDroppedLabel').textContent = 'Not Attempted';
    document.getElementById('kpiParticipantsCard').dataset.kpiLabel = isCourseOverview ? 'Unique Learners' : 'Total Enrollment';
    document.getElementById('kpiTotalEnrollmentsCard').dataset.kpiLabel = isCourseOverview ? 'Course Enrollments' : 'Current Total Enrollment';
    document.getElementById('kpiParticipantsCard').setAttribute('title', isCourseOverview ? 'Unique people enrolled anywhere in active courses.' : 'Unique participants in this course.');
    document.getElementById('kpiTotalEnrollmentsCard').setAttribute('title', 'Learner-course records. The same learner is counted once per course.');
    document.getElementById('kpiAttemptedCard').dataset.kpiLabel = 'Attempted';
    document.getElementById('kpiPassedCard').dataset.kpiLabel = 'Passed';
    document.getElementById('kpiCertifiedLabel').textContent = 'Certificates Issued';
    document.getElementById('kpiCertifiedCard').dataset.kpiLabel = 'Certificates Issued';
    document.getElementById('kpiFailedCard').dataset.kpiLabel = 'Failed';
    document.getElementById('kpiDroppedCard').dataset.kpiMetric = 'not_attempted';
    document.getElementById('kpiDroppedCard').dataset.kpiLabel = 'Not Attempted';
    let barMetric = requestedBarMetric;

    const barTitleBase = isCourseOverview ? 'Department' : (isQuiz ? 'Quiz' : 'Course');
    const barTitleMetric = (barMetric === 'notattempted') ? 'Not Attempted %'
        : (barMetric === 'fail') ? 'Fail %'
            : (barMetric === 'pass') ? (isCourseOverview ? 'Pass Readiness' : 'Pass %')
                : (isCourseOverview ? 'Readiness Score' : 'Completion');
    document.getElementById('barChartTitle').textContent = `${barTitleBase} ${barTitleMetric} by Department`;

    document.getElementById('kpiParticipants').textContent = numberFmt.format(data.participants || 0);
    renderKpiTrend('kpiParticipantsTrend', (data.trends && data.trends.participants) ? data.trends.participants : null);
    const totalEnrollmentsEl = document.getElementById('kpiTotalEnrollments');
    if (totalEnrollmentsEl) {
        totalEnrollmentsEl.textContent = numberFmt.format(data.total_enrollments || 0);
    }
    if (isQuiz) {
        document.getElementById('kpiAttempted').textContent = numberFmt.format(data.attempted || 0);
    }
    document.getElementById('kpiPassed').textContent = numberFmt.format(data.passed || 0);
    document.getElementById('kpiCertified').textContent = numberFmt.format(data.certified || 0);
    document.getElementById('kpiFailed').textContent = numberFmt.format(data.failed || 0);
    const totalEnrollment = Number(data.participants || 0);
    const currentEnrollment = Number(data.total_enrollments || 0);
    const notAttemptedCount = Number(
        data.not_attempted != null && data.not_attempted !== ''
            ? data.not_attempted
            : Math.max(0, currentEnrollment - Number(data.attempted || 0))
    );
    document.getElementById('kpiDropped').textContent = numberFmt.format(notAttemptedCount);

    // ── Platform stat row ──────────────────────────────────────────────────
    (function updateStatRow() {
        var setS = function(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };
        setS('statTotalStudents',  numberFmt.format(data.total_students  || 0));
        setS('statActiveCourses',  numberFmt.format(data.active_courses  || 0));
        setS('statCompletionRate', (data.completion_rate || 0) + '%');
        setS('statPendingModules', numberFmt.format(data.pending_modules || 0));
        const activeLearners = Number((data.engagement && data.engagement.Active) || 0);
        const inactiveLearners = Math.max(0, Number(data.total_students || 0) - activeLearners);
        setS('statActiveUsers',    numberFmt.format(activeLearners));
        setS('statInactiveUsers',  numberFmt.format(inactiveLearners));
        setS('statTotalStudentsLabel', Number(courseid || 0) > 0 ? 'Course Learners' : 'Unique Learners');
        setS('statCompletionRateLabel', Number(courseid || 0) > 0 ? 'Course Completion' : 'Enrollment Completion');
        setS('statPendingModulesLabel', Number(courseid || 0) > 0 ? 'Modules Pending' : 'Tracked Modules Pending');
        var lbl = document.getElementById('kpiSectionLabel');
        if (lbl) lbl.textContent = 'Course Outcome KPIs';
    })();

    // ── KPI percentages ────────────────────────────────────────────────────
    function setKpiPctLines(elId, lines) {
        const el = document.getElementById(elId);
        if (!el) {
            return;
        }
        const visibleLines = (lines || []).filter(function(line) {
            return line && (line.text || Number(line.denominator || 0) > 0);
        });
        if (!visibleLines.length) {
            el.innerHTML = '';
            el.style.display = 'none';
            return;
        }
        el.innerHTML = visibleLines.map(function(line) {
            if (line.text) {
                return '<span class="admindash-kpi__pct-caption">' + escapeHtml(line.text) + '</span>';
            }
            const pct = (Number(line.numerator || 0) / Number(line.denominator || 0)) * 100;
            return '<span class="admindash-kpi__pct-value">' + pct.toFixed(1) + '%</span>'
                + '<span class="admindash-kpi__pct-caption">of ' + line.caption + '</span>';
        }).join('');
        el.style.display = '';
    }

    function setKpiPctOfTotal(elId, numerator) {
        setKpiPctLines(elId, [{
            numerator: numerator,
            denominator: totalEnrollment,
            caption: isCourseOverview ? 'unique learners' : 'total enrollment'
        }]);
    }

    function setKpiOutcomePct(elId, numerator) {
        if (isCourseOverview) {
            setKpiPctLines(elId, [{
                numerator: numerator,
                denominator: currentEnrollment,
                caption: 'course enrollments'
            }]);
            return;
        }
        setKpiPctLines(elId, [
            {
                numerator: numerator,
                denominator: totalEnrollment,
                caption: 'total enrollment'
            },
            {
                numerator: numerator,
                denominator: currentEnrollment,
                caption: 'current enrollment'
            }
        ]);
    }

    if (isCourseOverview) {
        const avgCourses = totalEnrollment > 0 ? (currentEnrollment / totalEnrollment).toFixed(1) : '0.0';
        setKpiPctLines('kpiTotalEnrollmentsPct', [{ text: avgCourses + ' courses per learner' }]);
    } else {
        setKpiPctOfTotal('kpiTotalEnrollmentsPct', currentEnrollment);
    }
    setKpiOutcomePct('kpiAttemptedPct', data.attempted);
    setKpiOutcomePct('kpiDroppedPct', notAttemptedCount);
    setKpiOutcomePct('kpiPassedPct', data.passed);
    setKpiOutcomePct('kpiCertifiedPct', data.certified);
    setKpiOutcomePct('kpiFailedPct', data.failed);

    // ── Resigned Midway card (course filter only) ──────────────────────
    const resignedCard = document.getElementById('kpiResignedCard');
    const resignedVal = Number(data.resigned_midcourse || 0);
    if (resignedCard) {
        resignedCard.style.display = '';
        resignedCard.setAttribute('title', 'Distinct resigned participants. In overview, the same participant is counted once even across multiple courses.');
        document.getElementById('kpiResigned').textContent = numberFmt.format(resignedVal);
        setKpiPctOfTotal('kpiResignedPct', resignedVal);
    }
    renderKpiTrend('kpiAttemptedTrend', (data.trends && data.trends.attempted) ? data.trends.attempted : null);
    renderKpiTrend('kpiPassedTrend', (data.trends && data.trends.passed) ? data.trends.passed : null);
    renderKpiTrend('kpiCertifiedTrend', (data.trends && data.trends.certified) ? data.trends.certified : null);
    renderKpiTrend('kpiFailedTrend', (data.trends && data.trends.failed) ? data.trends.failed : null);
    renderKpiTrend('kpiDroppedTrend', (data.trends && data.trends.not_attempted)
        ? data.trends.not_attempted
        : null);
    renderKpiPieChart(data, isQuiz, Number(courseid || 0) > 0);

    const barCtx = document.getElementById('barChart').getContext('2d');
    const series = (barMetric === 'notattempted')
        ? (data.bar_data_notattempted || [])
        : (barMetric === 'fail')
            ? (data.bar_data_fail || [])
            : (barMetric === 'pass')
                ? (data.bar_data_pass || [])
                : (data.bar_data_completion || data.bar_data || []);

    const barLabels = series.map(d => d.department);
    const barValues = series.map(d => d.completion);
    const barEmpty = barValues.length === 0 || barValues.every(v => Number(v) === 0);
    document.getElementById('barEmptyMsg').style.display = barEmpty ? 'block' : 'none';

    if (barChartInstance) barChartInstance.destroy();
    barChartInstance = new Chart(barCtx, {
        type: 'bar',
        plugins: [barValueLabelsPlugin],
        data: {
            labels: barLabels,
            datasets: [{
                label: (barMetric === 'notattempted') ? 'Not Attempted %'
                    : (barMetric === 'fail') ? 'Fail %'
                        : (barMetric === 'pass') ? (isCourseOverview ? 'Pass readiness score' : 'Pass %')
                            : (isCourseOverview ? 'Readiness score' : 'Completion %'),
                data: barValues,
                backgroundColor: ['#0f1b3a','#b9c6ff','#ffd1f3','#ffe48c','#ff9aa0','#b6ffe9','#ffd1e8'],
                borderRadius: 8,
                categoryPercentage: 0.58,
                barPercentage: 0.68,
                maxBarThickness: 30
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                admindashBarValueLabels: {
                    enabled: barMetric === 'pass',
                    color: '#0b1b3d',
                    fontSize: 11,
                    offset: 10,
                    formatter(value) {
                        return `${Math.round(Number(value) || 0)}%`;
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const row = series[context.dataIndex] || {};
                            const value = Math.round(Number(context.parsed.y || 0));
                            const raw = row.rawpct !== undefined && row.rawpct !== null
                                ? Math.round(Number(row.rawpct || 0))
                                : value;
                            const courseCount = Number(row.coursecount || 0);
                            const enrolCount = Number(row.enrollmentcount || 0);
                            const lines = [];
                            if (isCourseOverview && (barMetric === 'completion' || barMetric === 'pass')) {
                                lines.push('Readiness score: ' + value + '%');
                                lines.push('Raw rate: ' + raw + '%');
                                lines.push('Coverage: ' + courseCount + ' course' + (courseCount === 1 ? '' : 's') + ', ' + enrolCount + ' enrollments');
                                return lines;
                            }
                            lines.push(context.dataset.label + ': ' + value + '%');
                            if (courseCount > 0) {
                                lines.push('Coverage: ' + courseCount + ' course' + (courseCount === 1 ? '' : 's') + ', ' + enrolCount + ' enrollments');
                            }
                            return lines;
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, max: 100 },
                x: {
                    ticks: {
                        color: '#0b1b3d'
                    }
                }
            }
        }
    });

    const hasCourse = String(courseid || '0') !== '0';
    renderActivityFeed(data.live_feed || [], hasCourse);

    // Content engagement (last 30 days): PDF views + SuperVideo watch time.
    const enrolCtx = document.getElementById('enrolChart').getContext('2d');
    if (enrolChartInstance) enrolChartInstance.destroy();
    const titleEl = document.getElementById('trendChartTitle');

    titleEl.textContent = 'Content Engagement (30 days)';
    const pdfRows = (data.pdf_view_series || []);
    const svRows = (data.supervideo_watch_series || []);
    const labels = (pdfRows.length ? pdfRows : svRows).map(p => p.day);
    const pdfViews = pdfRows.map(p => Number(p.views || 0));
    const svMinutes = svRows.map(p => Math.round((Number(p.seconds || 0) / 60) * 10) / 10);

    const isEmpty = (pdfViews.length === 0 || pdfViews.every(v => v === 0))
        && (svMinutes.length === 0 || svMinutes.every(v => v === 0));
    const emptyMsg = document.getElementById('enrolEmptyMsg');
    if (emptyMsg) {
        emptyMsg.textContent = 'No engagement recorded for the selected parameters in the last 30 days.';
        emptyMsg.style.display = isEmpty ? 'block' : 'none';
    }

    if (!isEmpty) {
        enrolChartInstance = new Chart(enrolCtx, {
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'PDF Views',
                    data: pdfViews,
                    backgroundColor: 'rgba(59,130,246,0.25)',
                    borderColor: 'rgba(59,130,246,0.9)',
                    borderWidth: 1,
                    yAxisID: 'y',
                    borderRadius: 6,
                },
                {
                    type: 'line',
                    label: 'SuperVideo Watch (min)',
                    data: svMinutes,
                    borderColor: '#0f1b3a',
                    backgroundColor: 'rgba(15,27,58,0.10)',
                    tension: 0.25,
                    borderWidth: 2,
                    pointRadius: 2,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'PDF Views' } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Watch (min)' } }
            }
        }
        });
    } // end if (!isEmpty) for engagement chart

    renderCourseProgress(data.module_progress || [], hasCourse);

    // Skill Gap Analysis (Radar).
    renderSkillGap(data.skill_gap || null);

    // Participant performance (horizontal bar leaderboard).
    renderPerformance(data.performance_leaderboard || null, hasCourse);
    renderAtRisk(data.at_risk_participants || [], hasCourse, Boolean(data.at_risk_course_running));
}

async function loadLiveFeed() {
    if (liveFeedState.loading) {
        return;
    }

    liveFeedState.loading = true;
    const requestId = ++liveFeedState.requestId;

    try {
        const courseid = document.getElementById('courseSelect').value;
        const department = document.getElementById('deptSelect').value;
        const url = new URL('data.php', window.location.href);
        url.searchParams.set('mode', 'live_feed');
        url.searchParams.set('courseid', courseid);
        url.searchParams.set('department', department);
        url.searchParams.set('_', String(Date.now()));

        const res = await fetch(url.toString(), { cache: 'no-store' });
        const data = await res.json();
        if (requestId !== liveFeedState.requestId) {
            return;
        }

        renderActivityFeed(data.live_feed || [], String(courseid || '0') !== '0');
        resetDashboardTimerCycle();
    } catch (error) {
        // Keep the latest rendered feed if refresh fails.
        resetDashboardTimerCycle();
    } finally {
        if (requestId === liveFeedState.requestId) {
            liveFeedState.loading = false;
        }
    }
}

function setupLiveFeedRefresh() {
    if (liveFeedState.intervalId) {
        window.clearInterval(liveFeedState.intervalId);
    }

    liveFeedState.intervalId = window.setInterval(function() {
        if (document.hidden) {
            return;
        }
        loadLiveFeed();
    }, liveFeedState.refreshEveryMs);

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            loadLiveFeed();
        }
    });
}

function renderActivityFeed(rows, hasCourse) {
    const wrap = document.getElementById('activityFeedWrap');
    if (!wrap) {
        return;
    }

    const items = Array.isArray(rows) ? rows : [];
    if (!items.length) {
        wrap.innerHTML = '<div class="admindash-widget-empty">No live activity found for the selected filters.</div>';
        return;
    }

    wrap.innerHTML = items.map(function(item) {
        const name = item.name || 'Participant';
        const action = item.action || 'did something';
        const course = item.course || '';
        const timestamp = item.timestamp || '';
        const meta = [course, timestamp].filter(Boolean).join(' • ');
        return ''
            + '<div class="admindash-livefeed__item">'
            + '<div class="admindash-livefeed__avatar admindash-livefeed__avatar--' + escapeHtml(String(item.avatar || 'neutral')) + '">' + getLiveFeedAvatarMarkup(String(item.avatar || 'neutral')) + '</div>'
            + '<div class="admindash-livefeed__body">'
            + '<div class="admindash-livefeed__line"><strong>' + escapeHtml(name) + '</strong> ' + escapeHtml(action) + '</div>'
            + '<div class="admindash-livefeed__meta">' + escapeHtml(meta) + '</div>'
            + '</div>'
            + '</div>';
    }).join('');
}

function renderCourseProgress(rows, hasCourse) {
    const wrap = document.getElementById('courseProgressWrap');
    if (!wrap) {
        return;
    }

    const items = Array.isArray(rows) ? rows : [];
    if (!items.length) {
        wrap.innerHTML = '<div class="admindash-widget-empty">'
            + (hasCourse ? 'No course completion data found for the selected filters.' : 'No recent course schedule data found.')
            + '</div>';
        return;
    }

    wrap.innerHTML = items.map(function(item, index) {
        const label = item.module || item.name || ('Module ' + (index + 1));
        const percent = Math.max(0, Math.min(100, Math.round(Number(item.completion || item.percent || 0))));
        return ''
            + '<div class="admindash-courseprogress__item">'
            + '<div class="admindash-courseprogress__head">'
            + '<div class="course-progress-name">' + escapeHtml(label) + '</div>'
            + '<div class="course-progress-percent">' + percent + '%</div>'
            + '</div>'
            + '<div class="admindash-courseprogress__track"><div class="admindash-courseprogress__fill" style="width:' + percent + '%"></div></div>'
            + '</div>';
    }).join('');
}

function renderAtRisk(rows, hasCourse, courseRunning) {
    const metaEl = document.getElementById('atRiskMeta');
    const emptyEl = document.getElementById('atRiskEmptyMsg');
    const tableWrap = document.getElementById('atRiskTableWrap');
    const tbody = document.getElementById('atRiskTableBody');
    if (!metaEl || !emptyEl || !tableWrap || !tbody) {
        return;
    }

    const items = Array.isArray(rows) ? rows : [];
    atRiskState = {
        rows: items,
        courseRunning: courseRunning === true,
        loading: false
    };

    const titleEl = document.querySelector('#atRiskSection h5');

    if (!hasCourse) {
        // Overview mode: show network-wide top-10 from every running course.
        if (titleEl) {
            titleEl.textContent = 'At-Risk — Network Wide';
        }
        if (!items.length) {
            metaEl.textContent = 'Network health looks good — no participants flagged across active courses.';
            emptyEl.textContent = 'Network Health Strong: 0 participants currently flagged as at-risk across active courses.';
            emptyEl.style.display = 'block';
            tableWrap.style.display = 'none';
            tbody.innerHTML = '';
            setAtRiskReminderState({loading: false, statusText: ''});
            return;
        }
        emptyEl.style.display = 'none';
        tableWrap.style.display = 'block';
        metaEl.textContent = `${items.length} participant${items.length === 1 ? '' : 's'} flagged across active courses — top ${items.length} by risk score.`;
        setAtRiskReminderState({loading: false, statusText: ''});
    } else {
        // Filtered mode: course-specific at-risk.
        if (titleEl) {
            titleEl.textContent = 'At-Risk Participants';
        }
        if (!courseRunning) {
            metaEl.textContent = 'At-risk participants are only shown for currently running courses.';
            emptyEl.style.display = 'block';
            tableWrap.style.display = 'none';
            tbody.innerHTML = '';
            setAtRiskReminderState({loading: false, statusText: ''});
            return;
        }
        if (!items.length) {
            metaEl.textContent = 'No participants currently need urgent intervention in this running course.';
            emptyEl.style.display = 'block';
            tableWrap.style.display = 'none';
            tbody.innerHTML = '';
            setAtRiskReminderState({loading: false, statusText: ''});
            return;
        }
        emptyEl.style.display = 'none';
        tableWrap.style.display = 'block';
        metaEl.textContent = `${items.length} participant${items.length === 1 ? '' : 's'} need immediate follow-up.`;
        setAtRiskReminderState({
            loading: false,
            statusText: `Send a chat reminder to ${items.length} at-risk participant${items.length === 1 ? '' : 's'}.`
        });
    }

    tbody.innerHTML = items.map(function(item) {
        const reasons = Array.isArray(item.reasons) ? item.reasons : [];
        const reasonHtml = reasons.map(function(reason) {
            return '<span class="admindash-risk-reason">' + escapeHtml(reason) + '</span>';
        }).join(' ');

        return ''
            + '<tr class="admindash-risk-row" data-risk-userid="' + escapeHtml(String(item.userid || 0)) + '" tabindex="0" role="button" aria-label="Open risk details for ' + escapeHtml(item.name || 'participant') + '">'
            + '<td>'
            + '<div class="admindash-risk-name"><span class="admindash-risk-flag" aria-hidden="true">&#9888;</span><span>' + escapeHtml(item.name || 'Participant') + '</span></div>'
            + '<div class="admindash-risk-score text-muted small">Risk score ' + escapeHtml(String(item.risk_score || 0)) + '/3</div>'
            + '</td>'
            + '<td>' + escapeHtml(item.department || '-') + '</td>'
            + '<td>' + escapeHtml(item.coursefullname || '-') + '</td>'
            + '<td>' + (reasonHtml || '<span class="text-muted small">No risk detail available.</span>') + '</td>'
            + '</tr>';
    }).join('');
}

function renderPerformance(leaderboard, hasCourse) {
    const canvas = document.getElementById('performanceChart');
    const emptyMsg = document.getElementById('performanceEmptyMsg');
    if (!canvas || !emptyMsg) {
        return;
    }

    performanceState.leaderboard = leaderboard || null;
    const participantItems = (leaderboard && Array.isArray(leaderboard.participants))
        ? leaderboard.participants
        : ((leaderboard && Array.isArray(leaderboard.items)) ? leaderboard.items : []);
    const clinicItems = (leaderboard && Array.isArray(leaderboard.clinics)) ? leaderboard.clinics : [];
    const mode = performanceState.mode === 'clinics' ? 'clinics' : 'participants';
    const items = mode === 'clinics' ? clinicItems : participantItems;
    const total = Number(leaderboard && leaderboard.totaltrackable) || 0;

    document.querySelectorAll('[data-performance-mode]').forEach(function(button) {
        const active = (button.getAttribute('data-performance-mode') || 'participants') === mode;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    if (performanceChartInstance) {
        performanceChartInstance.destroy();
        performanceChartInstance = null;
    }

    if (!hasCourse) {
        emptyMsg.textContent = 'Select a course to view performance leaders.';
        emptyMsg.style.display = 'block';
        return;
    }

    const values = items.map(i => Number(i.overall) || 0);
    const empty = items.length === 0 || values.every(v => v === 0);
    emptyMsg.textContent = empty
        ? (mode === 'clinics'
            ? 'No clinic performance data found for the selected filters.'
            : 'No participant performance data found for the selected filters.')
        : '';
    emptyMsg.style.display = empty ? 'block' : 'none';
    if (empty) {
        return;
    }

    const labels = items.map(function(item) {
        if (mode === 'clinics') {
            return item.name || 'Clinic';
        }
        const name = item.name || 'Participant';
        const department = String(item.department || '').trim();
        return department ? `${name} (${department})` : name;
    });
    const dynamicMax = Math.max(10, Math.ceil(Math.max.apply(null, values) * 1.1));

    const ctx = canvas.getContext('2d');
    performanceChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: mode === 'clinics' ? 'Clinic Performance %' : 'Participant Performance %',
                data: values,
                backgroundColor: '#3b82f6',
                borderRadius: 8,
                barThickness: 14,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            const i = items[ctx.dataIndex] || {};
                            const pct = Number(i.overall) || 0;
                            if (mode === 'clinics') {
                                const clinicParts = [
                                    `Overall: ${pct}%`,
                                    `Participants: ${Number(i.participantcount) || 0}`,
                                    `Completion: ${Number(i.completionpct ?? 0)}%`,
                                ];
                                if (i.ontimepct !== null && i.ontimepct !== undefined) {
                                    clinicParts.push(`On-time: ${Number(i.ontimepct)}%`);
                                }
                                if (i.attemptsefficiencypct !== null && i.attemptsefficiencypct !== undefined) {
                                    clinicParts.push(`Less attempts score: ${Number(i.attemptsefficiencypct)}% (${Number(i.attemptedquizcount) || 0}/${Number(i.totalattempts) || 0})`);
                                }
                                if (i.goodgradepct !== null && i.goodgradepct !== undefined) {
                                    clinicParts.push(`Good grade: ${Number(i.goodgradepct)}%`);
                                }
                                if (i.highestgradepct !== null && i.highestgradepct !== undefined) {
                                    clinicParts.push(`Highest grade: ${Number(i.highestgradepct)}%`);
                                }
                                return clinicParts;
                            }

                            const done = Number(i.done) || 0;
                            const totalLocal = Number(i.total) || total;
                            const parts = [];
                            if (totalLocal > 0) {
                                parts.push(`Overall: ${pct}%`);
                                parts.push(`Completion: ${Number(i.completionpct ?? 0)}% (${done}/${totalLocal})`);
                            } else {
                                parts.push(`Overall: ${pct}%`);
                            }

                            if (i.ontimepct !== null && i.ontimepct !== undefined) {
                                const otDone = Number(i.ontimedone) || 0;
                                const otTotal = Number(i.ontimetotal) || 0;
                                parts.push(`On-time: ${Number(i.ontimepct)}% (${otDone}/${otTotal})`);
                            }

                            if (i.gradepct !== null && i.gradepct !== undefined) {
                                parts.push(`Grade: ${Number(i.gradepct)}%`);
                            }

                            if (i.attemptsefficiencypct !== null && i.attemptsefficiencypct !== undefined) {
                                const attemptedQuizzes = Number(i.attemptedquizcount) || 0;
                                const totalAttempts = Number(i.totalattempts) || 0;
                                parts.push(`Less attempts score: ${Number(i.attemptsefficiencypct)}% (${attemptedQuizzes}/${totalAttempts})`);
                            }

                            if (i.goodgradepct !== null && i.goodgradepct !== undefined) {
                                parts.push(`Good grade: ${Number(i.goodgradepct)}%`);
                            }

                            if (i.highestgradepct !== null && i.highestgradepct !== undefined) {
                                parts.push(`Highest grade: ${Number(i.highestgradepct)}%`);
                            }

                            return parts;
                        }
                    }
                }
            },
            scales: {
                x: { beginAtZero: true, max: dynamicMax },
                y: { ticks: { autoSkip: false } }
            }
        }
    });
}

function renderSkillGap(skillgap) {
    const canvas = document.getElementById('skillGapChart');
    const emptyMsg = document.getElementById('skillGapEmptyMsg');
    if (!canvas || !emptyMsg) {
        return;
    }

    const isOverviewMode = (dashboardViewMode === 'overview');
    const titleEl = document.querySelector('#skillGapCard h5');

    const labels = (skillgap && Array.isArray(skillgap.labels)) ? skillgap.labels : [];
    const required = (skillgap && Array.isArray(skillgap.required)) ? skillgap.required : [];
    const current = (skillgap && Array.isArray(skillgap.current)) ? skillgap.current : [];

    const hasData = labels.length > 0 && required.length === labels.length && current.length === labels.length;
    emptyMsg.style.display = hasData ? 'none' : 'block';

    if (skillGapChartInstance) {
        skillGapChartInstance.destroy();
        skillGapChartInstance = null;
    }

    if (!hasData) {
        if (titleEl) {
            titleEl.textContent = isOverviewMode ? 'Department Readiness' : 'Skill Gap Analysis';
        }
        emptyMsg.textContent = isOverviewMode
            ? 'No department completion data available yet.'
            : 'No skill data yet for the selected course.';
        return;
    }

    if (titleEl) {
        titleEl.textContent = isOverviewMode ? 'Department Readiness (Network)' : 'Skill Gap Analysis';
    }
    emptyMsg.textContent = isOverviewMode
        ? 'No department completion data available yet.'
        : 'No skill data yet for the selected course.';

    const pointColors = current.map((val, idx) => {
        const gap = (Number(required[idx]) || 0) - (Number(val) || 0);
        return gap >= 10 ? '#ef4444' : '#10b981';
    });

    const ctx = canvas.getContext('2d');
    skillGapChartInstance = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: isOverviewMode ? 'Target (80%)' : 'Required Standard',
                    data: required,
                    borderColor: 'rgba(15,27,58,0.85)',
                    backgroundColor: 'rgba(15,27,58,0.08)',
                    pointBackgroundColor: 'rgba(15,27,58,0.85)',
                    borderWidth: 2,
                    pointRadius: 3,
                },
                {
                    label: isOverviewMode ? 'Coverage-adjusted readiness' : 'Current Staff Average',
                    data: current,
                    borderColor: 'rgba(59,130,246,0.95)',
                    backgroundColor: 'rgba(59,130,246,0.10)',
                    pointBackgroundColor: pointColors,
                    borderWidth: 2,
                    pointRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    pointLabels: {
                        font: {
                            size: labels.length > 10 ? 9 : 11
                        }
                    },
                    ticks: {
                        display: false,
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// ─── Executive Overview helpers ──────────────────────────────────────────────

function getActiveDashboardMode() {
    const courseId = String(document.getElementById('courseSelect')?.value || '0');
    // Only a specific course switches the layout (pie + leaderboard). Department / module alone
    // still use the platform overview shell so the courses chart and overview widgets stay visible.
    return courseId !== '0' ? 'filtered' : 'overview';
}

function updateFilterState() {
    const courseId  = String(document.getElementById('courseSelect')?.value || '0');
    const dept      = String(document.getElementById('deptSelect')?.value || '');
    const moduleId  = String(document.getElementById('moduleSelect')?.value || '0');
    const activeCount = (courseId !== '0' ? 1 : 0) + (dept !== '' ? 1 : 0) + (moduleId !== '0' ? 1 : 0);

    const badge = document.getElementById('activeFilterBadge');
    if (badge) {
        badge.textContent = activeCount > 0 ? String(activeCount) : '';
        badge.classList.toggle('has-active', activeCount > 0);
    }

    const resetBtn = document.getElementById('filterResetBtn');
    if (resetBtn) resetBtn.style.display = activeCount > 0 ? '' : 'none';

    const toggleActive = function(id, active) {
        document.getElementById(id)?.classList.toggle('is-active', active);
    };
    toggleActive('courseSelect', courseId !== '0');
    toggleActive('deptSelect',  dept !== '');
    toggleActive('moduleSelect', moduleId !== '0');
}

function switchDashboardMode(mode) {
    dashboardViewMode = mode;
    const isOverview = (mode === 'overview');
    const setVisible = function(id, visible) {
        const el = document.getElementById(id);
        if (el) {
            el.style.display = visible ? '' : 'none';
        }
    };
    setVisible('coursesOverviewCard', isOverview);
    setVisible('kpiPieCard', !isOverview);
    setVisible('performanceCard', !isOverview);

    // In overview mode always show Completion % in the bar chart — other
    // metrics (Fail %, Pass %) need course-level grade data which is only
    // meaningful once a course is selected.
    if (isOverview) {
        const bms = document.getElementById('barMetricSelect');
        if (bms && bms.value !== 'completion') {
            bms.value = 'completion';
        }
    }
}

function updateOverviewBanner(data) {
    // Executive Overview banner removed.
}

function renderCoursesOverview(leaderboard) {
    const emptyEl = document.getElementById('coursesOverviewEmpty');
    const wrap = document.getElementById('coursesOverviewWrap');
    const topEl = document.getElementById('coursesOverviewTop');
    const restEl = document.getElementById('coursesOverviewRest');

    if (topEl) {
        topEl.innerHTML = '';
    }
    if (restEl) {
        restEl.innerHTML = '';
    }

    overviewLeaderState.leaderboard = leaderboard || null;
    const participantItems = (leaderboard && Array.isArray(leaderboard.participants)) ? leaderboard.participants : [];
    const clinicItems = (leaderboard && Array.isArray(leaderboard.clinics)) ? leaderboard.clinics : [];
    const mode = overviewLeaderState.mode === 'clinics' ? 'clinics' : 'participants';
    const items = mode === 'clinics' ? clinicItems : participantItems;
    const selectedCourseIds = (leaderboard && Array.isArray(leaderboard.selected_courseids)) ? leaderboard.selected_courseids : [];
    const summaryEl = document.getElementById('coursesOverviewSummary');
    if (summaryEl) {
        const valueEl = summaryEl.querySelector('.admindash-overview-leaders__summary-value');
        const labelEl = summaryEl.querySelector('.admindash-overview-leaders__summary-label');
        const selectedCount = selectedCourseIds.length;
        if (valueEl) {
            valueEl.textContent = selectedCount ? numberFmt.format(selectedCount) : 'All';
        }
        if (labelEl) {
            labelEl.textContent = (selectedCount === 1 ? 'course selected' : 'courses selected') + ' - ' + numberFmt.format(items.length) + ' ' + (mode === 'clinics' ? 'clinics' : 'participants');
        }
    }

    document.querySelectorAll('[data-overview-leader-mode]').forEach(function(button) {
        const active = (button.getAttribute('data-overview-leader-mode') || 'participants') === mode;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    if (!items.length) {
        if (emptyEl) {
            emptyEl.style.display = 'block';
        }
        if (wrap) {
            wrap.style.display = 'none';
        }
        return;
    }

    if (emptyEl) {
        emptyEl.style.display = 'none';
    }
    if (wrap) {
        wrap.style.display = '';
        wrap.style.height = '';
    }

    const getName = function(item) {
        if (mode === 'clinics') {
            return item.name || 'Clinic';
        }
        const department = String(item.department || '').trim();
        return department ? `${item.name || 'Participant'} (${department})` : (item.name || 'Participant');
    };
    const hasMetric = function(item, key) {
        return item[key] !== null && item[key] !== undefined && item[key] !== '';
    };
    const pctText = function(value) {
        return Number(value || 0) + '%';
    };
    const getMeta = function(item) {
        if (mode === 'clinics') {
            const bits = [
                numberFmt.format(Number(item.participantcount || 0)) + ' participants',
                pctText(item.completionpct) + ' completion'
            ];
            if (hasMetric(item, 'ontimepct')) {
                bits.push(pctText(item.ontimepct) + ' on-time');
            }
            if (hasMetric(item, 'attemptsefficiencypct')) {
                bits.push(pctText(item.attemptsefficiencypct) + ' attempt efficiency');
            }
            if (hasMetric(item, 'goodgradepct')) {
                bits.push(pctText(item.goodgradepct) + ' strong grade');
            }
            if (hasMetric(item, 'highestgradepct')) {
                bits.push(pctText(item.highestgradepct) + ' highest grade');
            }
            return bits;
        }
        const bits = [
            numberFmt.format(Number(item.coursecount || 0)) + ' courses',
            pctText(item.completionpct) + ' completion'
        ];
        if (hasMetric(item, 'ontimepct')) {
            bits.push(pctText(item.ontimepct) + ' on-time');
        }
        if (hasMetric(item, 'attemptsefficiencypct')) {
            bits.push(pctText(item.attemptsefficiencypct) + ' attempt efficiency');
        }
        if (hasMetric(item, 'goodgradepct')) {
            bits.push(pctText(item.goodgradepct) + ' strong grade');
        }
        if (hasMetric(item, 'highestgradepct')) {
            bits.push(pctText(item.highestgradepct) + ' highest grade');
        }
        return bits;
    };
    const scoreClass = function(score) {
        return score >= 70 ? 'is-strong' : (score >= 40 ? 'is-steady' : 'is-watch');
    };
    const makeTopCard = function(item, index) {
        const score = Number(item.overall || 0);
        const meta = getMeta(item).map(function(bit) {
            return '<span>' + escapeHtml(bit) + '</span>';
        }).join('');
        return '<article class="admindash-leader-card admindash-leader-card--rank' + (index + 1) + '">'
            + '<div class="admindash-leader-card__rank">#' + (index + 1) + '</div>'
            + '<div class="admindash-leader-card__body">'
            + '<div class="admindash-leader-card__name">' + escapeHtml(getName(item)) + '</div>'
            + '<div class="admindash-leader-card__meta">' + meta + '</div>'
            + '</div>'
            + '<div class="admindash-leader-card__score ' + scoreClass(score) + '">' + score + '%</div>'
            + '</article>';
    };
    const makeRestRow = function(item, index) {
        const rank = index + 4;
        const score = Number(item.overall || 0);
        const meta = getMeta(item).slice(0, 3).join(' - ');
        return '<div class="admindash-leader-row">'
            + '<div class="admindash-leader-row__rank">' + rank + '</div>'
            + '<div class="admindash-leader-row__main">'
            + '<div class="admindash-leader-row__name">' + escapeHtml(getName(item)) + '</div>'
            + '<div class="admindash-leader-row__meta">' + escapeHtml(meta) + '</div>'
            + '</div>'
            + '<div class="admindash-leader-row__score ' + scoreClass(score) + '">' + score + '%</div>'
            + '</div>';
    };

    const topItems = items.slice(0, 3);
    const restItems = items.slice(3, 10);
    if (topEl) {
        topEl.innerHTML = '<div class="admindash-overview-leaders__top-title">Top 3</div>' + topItems.map(makeTopCard).join('');
    }
    if (restEl) {
        restEl.innerHTML = restItems.length
            ? '<div class="admindash-overview-leaders__rest-title">Next 7</div>' + restItems.map(makeRestRow).join('')
            : '<div class="admindash-overview-leaders__rest-empty">No additional leaders to show.</div>';
    }
}

async function loadCoursesOverview() {
    const dept = String(document.getElementById('deptSelect')?.value || '');
    const select = document.getElementById('overviewCourseLeaderSelect');
    const selectedCourseIds = select ? [...select.selectedOptions].map(option => option.value).filter(Boolean) : [];
    const url = new URL('data.php', window.location.href);
    url.searchParams.set('mode', 'multi_course_leaders');
    url.searchParams.set('department', dept);
    url.searchParams.set('courseids', selectedCourseIds.join(','));
    url.searchParams.set('_', String(Date.now()));

    const emptyEl = document.getElementById('coursesOverviewEmpty');
    if (emptyEl) {
        emptyEl.style.display = 'none';
    }

    try {
        const res = await fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store' });
        let payload = {};
        try { payload = await res.json(); } catch (_) { /* non-JSON response */ }
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        renderCoursesOverview(payload);
    } catch (e) {
        console.error('[admindash] loadCoursesOverview:', e);
        if (emptyEl) {
            emptyEl.style.display = 'block';
        }
    }
}

// ─── End Executive Overview helpers ──────────────────────────────────────────

async function init() {
    await loadInitialMeta();
    setupOverviewCourseDropdown();
    const overviewSubtitle = document.querySelector('#coursesOverviewCard h5 + .text-muted');
    if (overviewSubtitle) {
        overviewSubtitle.textContent = 'Compare top participants and clinics across selected courses.';
    }
    setupDashboardTimer();
    updateFilterState(); // reflect any pre-selected filter values on initial load

    // Start in overview mode (hidden until data loads to avoid flash)
    switchDashboardMode('overview');
    try { await loadDashboardData(); } catch (e) { console.error('[admindash] loadDashboardData failed:', e); }
    try { await loadLiveFeed(); } catch (e) { console.error('[admindash] loadLiveFeed failed:', e); }
    try { await loadCoursesOverview(); } catch (e) { console.error('[admindash] loadCoursesOverview failed:', e); }
    loadUpcomingEvent(); // fire-and-forget; updates timer card independently
    setupLiveFeedRefresh();

    document.querySelectorAll('.admindash-kpi[data-kpi-metric]').forEach(function(card) {
        const activate = function() {
            if (card.style.display === 'none') {
                return;
            }
            openKpiUsersModal(card.dataset.kpiMetric || '', card.dataset.kpiLabel || 'KPI');
        };
        card.addEventListener('click', activate);
        card.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate();
            }
        });
    });

    const reminderButton = document.getElementById('kpiReminderButton');
    if (reminderButton) {
        reminderButton.addEventListener('click', sendKpiReminders);
    }

    const atRiskReminderButton = document.getElementById('atRiskReminderButton');
    if (atRiskReminderButton) {
        atRiskReminderButton.addEventListener('click', sendAtRiskReminders);
    }

    const atRiskTableBody = document.getElementById('atRiskTableBody');
    if (atRiskTableBody) {
        atRiskTableBody.addEventListener('click', function(event) {
            const row = event.target.closest('[data-risk-userid]');
            if (!row) {
                return;
            }
            openAtRiskDetails(Number(row.getAttribute('data-risk-userid') || 0));
        });
        atRiskTableBody.addEventListener('keydown', function(event) {
            const row = event.target.closest('[data-risk-userid]');
            if (!row) {
                return;
            }
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openAtRiskDetails(Number(row.getAttribute('data-risk-userid') || 0));
            }
        });
    }

    document.getElementById('courseSelect').addEventListener('change', async () => {
        updateFilterState();
        try { await reloadDependentMeta(); } catch (e) { console.error('[admindash] reloadDependentMeta:', e); }
        const mode = getActiveDashboardMode();
        switchDashboardMode(mode);
        try { await loadDashboardData(); } catch (e) { console.error('[admindash] loadDashboardData:', e); }
        try { await loadLiveFeed(); } catch (e) { console.error('[admindash] loadLiveFeed:', e); }
        if (mode === 'overview') {
            try { await loadCoursesOverview(); } catch (e) { console.error('[admindash] loadCoursesOverview:', e); }
        }
        loadUpcomingEvent(); // quiz timer when course selected; course timer when cleared
    });
    document.getElementById('deptSelect').addEventListener('change', async function() {
        updateFilterState();
        const mode = getActiveDashboardMode();
        switchDashboardMode(mode);
        try { await loadDashboardData(); } catch (e) { console.error('[admindash] loadDashboardData:', e); }
        try { await loadLiveFeed(); } catch (e) { console.error('[admindash] loadLiveFeed:', e); }
        if (mode === 'overview') {
            try { await loadCoursesOverview(); } catch (e) { console.error('[admindash] loadCoursesOverview:', e); }
        }
    });
    document.getElementById('moduleSelect').addEventListener('change', async function() {
        updateFilterState();
        const mode = getActiveDashboardMode();
        switchDashboardMode(mode);
        try { await loadDashboardData(); } catch (e) { console.error('[admindash] loadDashboardData:', e); }
    });
    document.getElementById('filterResetBtn')?.addEventListener('click', async function() {
        document.getElementById('courseSelect').value = '0';
        document.getElementById('deptSelect').value = '';
        document.getElementById('moduleSelect').value = '0';
        updateFilterState();
        try { await reloadDependentMeta(); } catch (e) {}
        const mode = getActiveDashboardMode();
        switchDashboardMode(mode);
        try { await loadDashboardData(); } catch (e) {}
        try { await loadLiveFeed(); } catch (e) {}
        try { await loadCoursesOverview(); } catch (e) {}
        loadUpcomingEvent();
    });
    document.getElementById('barMetricSelect').addEventListener('change', loadDashboardData);
    document.querySelectorAll('[data-performance-mode]').forEach(function(button) {
        button.addEventListener('click', function() {
            const requestedMode = button.getAttribute('data-performance-mode') || 'participants';
            performanceState.mode = (requestedMode === 'clinics') ? 'clinics' : 'participants';
            renderPerformance(performanceState.leaderboard || null, String(dashboardState.courseid || '0') !== '0');
        });
    });
    document.querySelectorAll('[data-overview-leader-mode]').forEach(function(button) {
        button.addEventListener('click', function() {
            const requestedMode = button.getAttribute('data-overview-leader-mode') || 'participants';
            overviewLeaderState.mode = (requestedMode === 'clinics') ? 'clinics' : 'participants';
            renderCoursesOverview(overviewLeaderState.leaderboard || null);
        });
    });
    document.getElementById('overviewCourseLeaderSelect')?.addEventListener('change', function() {
        syncOverviewCourseDropdownText();
        loadCoursesOverview().catch(function(e) {
            console.error('[admindash] loadCoursesOverview:', e);
        });
    });
}

init();

});
})();
</script>

<?php
local_admindashboard_render_footer();
