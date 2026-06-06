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
require_once(__DIR__ . '/metricslib.php');

local_admindashboard_setup_page('/local/admindashboard/sentiment_analyzer.php', 'Sentiment Analyzer', 'courseanalytics.sentiment');
local_admindashboard_render_header('courseanalytics.sentiment');

$courseid = optional_param('courseid', 0, PARAM_INT);
$sesskey = sesskey();
$meta = local_admindashboard_get_meta($courseid);
?>

<div class="admindash-sentiment-page">
    <header class="admindash-sentiment-hero" aria-labelledby="sentimentHeroTitle">
        <div class="admindash-sentiment-hero__glow admindash-sentiment-hero__glow--a" aria-hidden="true"></div>
        <div class="admindash-sentiment-hero__glow admindash-sentiment-hero__glow--b" aria-hidden="true"></div>
        <div class="admindash-sentiment-hero__inner">
            <div class="admindash-sentiment-hero__badge">
                <span class="admindash-sentiment-hero__badge-dot" aria-hidden="true"></span>
                <span><?php echo get_string('ui_sentiment_analyzer_ai_insights', 'local_admindashboard'); ?></span>
            </div>
            <h2 id="sentimentHeroTitle" class="admindash-sentiment-hero__title"><?php echo get_string('ui_sentiment_analyzer_sentiment_analyzer', 'local_admindashboard'); ?></h2>
            <p class="admindash-sentiment-hero__lead">
                <?php echo get_string('ui_sentiment_analyzer_explore_qualitative_tone_and_quantitative_ratings_from_course_f_ad6a2ef2', 'local_admindashboard'); ?>
            </p>
        </div>
    </header>

    <form method="get" class="admindash-filters admindash-sentiment-filter" action="<?php echo (new moodle_url('/local/admindashboard/sentiment_analyzer.php')); ?>">
        <div class="admindash-filters__header">
            <span class="admindash-filters__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 14 0A7 7 0 0 1 2 9Zm8.45 3.35a.75.75 0 1 1-1.05 1.05l-3.25-3.25a.75.75 0 0 1 0-1.06l3.25-3.25a.75.75 0 1 1 1.06 1.06L8.06 9l2.39 2.35Z" clip-rule="evenodd"/></svg>
            </span>
            <span class="admindash-filters__title"><?php echo get_string('ui_sentiment_analyzer_scope', 'local_admindashboard'); ?></span>
        </div>
        <span class="admindash-filters__sep" aria-hidden="true"></span>
        <div class="admindash-filters__fields">
            <div class="admindash-filter-group admindash-filter-group--grow">
                <label class="admindash-filter-group__label" for="courseSelect"><?php echo get_string('ui_sentiment_analyzer_course', 'local_admindashboard'); ?></label>
                <select id="courseSelect" name="courseid" class="form-select admindash-filter-group__select <?php echo $courseid > 0 ? 'is-active' : ''; ?>">
                    <option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>Choose a course...</option>
                    <?php foreach ($meta['courses'] as $course): ?>
                        <option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
                            <?php echo s($course['fullname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn admindash-sentiment-filter__submit">
                <span><?php echo get_string('ui_sentiment_analyzer_load_insights', 'local_admindashboard'); ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638l-3.158-3.157a.75.75 0 1 1 1.061-1.061l4.5 4.5a.75.75 0 0 1 0 1.061l-4.5 4.5a.75.75 0 1 1-1.061-1.061l3.158-3.157H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
            </button>
        </div>
    </form>

    <div id="sentimentAnalyzerRoot" class="admindash-sentiment-analyzer admindash-feedback card border-0">
        <div class="admindash-sentiment-analyzer__loading" aria-live="polite">
            <div class="admindash-sentiment-analyzer__loading-inner">
                <span class="admindash-sentiment-analyzer__spinner" aria-hidden="true"></span>
                <span class="admindash-sentiment-analyzer__loading-text"><?php echo get_string('ui_sentiment_analyzer_analyzing_feedback', 'local_admindashboard'); ?></span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="admindash-sentiment-toolbar">
                <div class="admindash-sentiment-toolbar__titles">
                    <p class="admindash-sentiment-toolbar__eyebrow"><?php echo get_string('ui_sentiment_analyzer_feedback_intelligence', 'local_admindashboard'); ?></p>
                    <h3 class="admindash-sentiment-toolbar__title"><?php echo get_string('ui_sentiment_analyzer_learner_voice_snapshot', 'local_admindashboard'); ?></h3>
                    <p class="admindash-feedback__subtitle mb-0" id="feedbackAnalyzerMeta"><?php echo get_string('ui_sentiment_analyzer_select_a_course_to_load_quantitative_scores_and_ai_sentiment', 'local_admindashboard'); ?></p>
                </div>
                <div class="admindash-sentiment-toolbar__badge-wrap">
                    <span class="admindash-sentiment-toolbar__badge" id="feedbackAnalyzerBadge">
                        <span class="admindash-sentiment-toolbar__badge-ico" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M10 2a.75.75 0 0 1 .75.75v1.271a3.375 3.375 0 0 1 2.658 2.658l1.271-.001a.75.75 0 0 1 .498 1.322l-1.272 1.272a3.375 3.375 0 0 1 0 4.756l1.272 1.272a.75.75 0 1 1-1.322.498l-1.271-.001a3.375 3.375 0 0 1-2.658 2.658l.001 1.271a.75.75 0 1 1-1.322 0l-.001-1.271a3.375 3.375 0 0 1-2.658-2.658l-1.271.001a.75.75 0 1 1-.498-1.322l1.272-1.272a3.375 3.375 0 0 1 0-4.756L4.34 7.59a.75.75 0 1 1 .498-1.322l1.271.001a3.375 3.375 0 0 1 2.658-2.658V2.75A.75.75 0 0 1 10 2Z"/></svg>
                        </span>
                        <?php echo get_string('ui_sentiment_analyzer_ready', 'local_admindashboard'); ?>
                    </span>
                </div>
            </div>

            <div class="row g-4 px-4 pb-4">
                <div class="col-lg-5">
                    <div class="admindash-sentiment-panel admindash-feedback__panel h-100">
                        <div class="admindash-sentiment-panel__head">
                            <span class="admindash-sentiment-panel__icon admindash-sentiment-panel__icon--chart" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M15.5 2.75a.75.75 0 0 1 .75.75v13a.75.75 0 0 1-.75.75h-3a.75.75 0 0 1-.75-.75v-13a.75.75 0 0 1 .75-.75h3ZM10 6.75A.75.75 0 0 1 10.75 6h3a.75.75 0 0 1 .75.75v9.5a.75.75 0 0 1-.75.75h-3a.75.75 0 0 1-.75-.75v-9.5Zm-5.5 4a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75v5.5a.75.75 0 0 1-.75.75h-3a.75.75 0 0 1-.75-.75v-5.5Zm-4 2a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-.75.75h-3a.75.75 0 0 1-.75-.75v-3.5Z"/></svg>
                            </span>
                            <div>
                                <h4 class="admindash-sentiment-panel__title"><?php echo get_string('ui_sentiment_analyzer_sentiment_mix', 'local_admindashboard'); ?></h4>
                                <p class="admindash-sentiment-panel__hint"><?php echo get_string('ui_sentiment_analyzer_ai_estimated_share_of_tone_across_open_comments', 'local_admindashboard'); ?></p>
                            </div>
                        </div>
                        <div class="admindash-feedback__chart-wrap admindash-sentiment-chart-wrap">
                            <canvas id="feedbackSentimentChart" height="260"></canvas>
                        </div>
                        <div id="feedbackSentimentStats" class="admindash-sentiment-split-stats" hidden></div>
                        <div id="feedbackSentimentEmpty" class="admindash-feedback__empty admindash-sentiment-empty" style="display:none">
                            <span class="admindash-sentiment-empty__ico" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.176C3.202 15.42 2 13.804 2 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
                            </span>
                            <?php echo get_string('ui_sentiment_analyzer_no_comments_available_for_sentiment_analysis_yet', 'local_admindashboard'); ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="admindash-sentiment-panel admindash-feedback__panel h-100">
                        <div class="admindash-sentiment-panel__head admindash-sentiment-panel__head--row">
                            <div class="d-flex align-items-start gap-3 flex-wrap justify-content-between w-100">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="admindash-sentiment-panel__icon admindash-sentiment-panel__icon--bars" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M4 16.5A1.5 1.5 0 0 1 2.5 15V5A1.5 1.5 0 0 1 4 3.5h12A1.5 1.5 0 0 1 17.5 5v10a1.5 1.5 0 0 1-1.5 1.5H4ZM4 5v10h12V5H4Z"/><path d="M6.75 7.5h1.5v5h-1.5v-5Zm3 2h1.5v3h-1.5v-3Zm3-3h1.5v6h-1.5v-6Z"/></svg>
                                    </span>
                                    <div>
                                        <h4 class="admindash-sentiment-panel__title"><?php echo get_string('ui_sentiment_analyzer_likert_averages', 'local_admindashboard'); ?></h4>
                                        <p class="admindash-sentiment-panel__hint"><?php echo get_string('ui_sentiment_analyzer_per_question_means_from_scaled_responses', 'local_admindashboard'); ?></p>
                                    </div>
                                </div>
                                <span id="feedbackOverallAverage" class="admindash-sentiment-score-pill"><?php echo get_string('ui_sentiment_analyzer_overall_5', 'local_admindashboard'); ?></span>
                            </div>
                        </div>
                        <div id="feedbackQuantBars" class="admindash-sentiment-quant-grid"></div>
                        <div id="feedbackQuantEmpty" class="admindash-feedback__empty admindash-sentiment-empty" style="display:none">
                            <span class="admindash-sentiment-empty__ico" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v7.125c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 3 20.25v-7.125ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                            </span>
                            <?php echo get_string('ui_sentiment_analyzer_no_quantitative_feedback_responses_yet', 'local_admindashboard'); ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="admindash-sentiment-panel admindash-feedback__panel h-100">
                        <div class="admindash-sentiment-panel__head">
                            <span class="admindash-sentiment-panel__icon admindash-sentiment-panel__icon--spark" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.395 2.553a1 1 0 0 0-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 0 0-.613 3.58 2.64 2.64 0 0 1-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 0 0 6.035 5.41c-.354.334-.62.767-.76 1.213-.144.465-.204.91-.204 1.306 0 .977.252 1.91.674 2.704.421.795 1.02 1.545 1.787 2.128.768.584 1.703.996 2.776 1.192 1.073.196 2.228.088 3.245-.311 1.018-.399 1.96-1.106 2.725-2.054.766-.948 1.34-2.13 1.658-3.455.317-1.325.373-2.762.161-4.137-.212-1.374-.684-2.652-1.366-3.708ZM6.756 15.763c.532.356 1.152.622 1.863.771 1.015.233 2.156.162 3.224-.247 1.067-.41 2.062-1.15 2.828-2.13.765-.98 1.36-2.19 1.675-3.512.315-1.322.37-2.75.16-4.11-.21-1.36-.678-2.62-1.35-3.67-.532-.886-1.19-1.65-1.93-2.25-.74-.6-1.56-1.04-2.42-1.28-.86-.24-1.75-.28-2.62-.12-.87.16-1.71.52-2.48 1.05-.77.53-1.45 1.24-2 2.08-.55.84-.98 1.79-1.26 2.81-.28 1.02-.41 2.11-.38 3.23.03 1.12.21 2.22.53 3.24.32 1.02.78 1.97 1.35 2.79Z" clip-rule="evenodd"/></svg>
                            </span>
                            <div>
                                <h4 class="admindash-sentiment-panel__title"><?php echo get_string('ui_sentiment_analyzer_trending_themes', 'local_admindashboard'); ?></h4>
                                <p class="admindash-sentiment-panel__hint"><?php echo get_string('ui_sentiment_analyzer_recurring_phrases_from_learner_comments', 'local_admindashboard'); ?></p>
                            </div>
                        </div>
                        <div id="feedbackKeywords" class="admindash-feedback__chips admindash-sentiment-chips"></div>
                        <div id="feedbackKeywordsEmpty" class="admindash-feedback__empty admindash-sentiment-empty" style="display:none">
                            <?php echo get_string('ui_sentiment_analyzer_no_recurring_themes_found_yet', 'local_admindashboard'); ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="admindash-sentiment-panel admindash-feedback__panel h-100">
                        <div class="admindash-sentiment-panel__head">
                            <span class="admindash-sentiment-panel__icon admindash-sentiment-panel__icon--flag" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3.25A.75.75 0 0 1 4 3v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V9.37a1.5 1.5 0 0 0-.56-1.17l-6-4.5a1.5 1.5 0 0 0-1.88 0l-1.94 1.45V3.75A.75.75 0 0 1 3 3.25Zm1.5 0V9.88l5.25-3.94 6 4.5V15H5V3.25Z" clip-rule="evenodd"/></svg>
                            </span>
                            <div>
                                <h4 class="admindash-sentiment-panel__title"><?php echo get_string('ui_sentiment_analyzer_highlighted_comments', 'local_admindashboard'); ?></h4>
                                <p class="admindash-sentiment-panel__hint"><?php echo get_string('ui_sentiment_analyzer_representative_excerpts_flagged_for_review_auto_scrolls_vertica_c3fa7e2f', 'local_admindashboard'); ?></p>
                            </div>
                        </div>
                        <div id="feedbackFlaggedCommentsViewport" class="admindash-sentiment-comments-marquee" style="display:none" role="region" aria-label="Highlighted comments">
                            <div id="feedbackFlaggedCommentsTrack" class="admindash-sentiment-comments-marquee__track"></div>
                        </div>
                        <div id="feedbackFlaggedEmpty" class="admindash-feedback__empty admindash-sentiment-empty" style="display:none">
                            <?php echo get_string('ui_sentiment_analyzer_no_notable_comments_available_yet', 'local_admindashboard'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <nav class="admindash-sentiment-next" aria-label="Course analytics">
        <span class="admindash-sentiment-next__label"><?php echo get_string('ui_sentiment_analyzer_continue_exploring', 'local_admindashboard'); ?></span>
        <div class="admindash-sentiment-next__links">
            <a class="admindash-sentiment-next__link" href="<?php echo (new moodle_url('/local/admindashboard/course_analytics.php')); ?>">Analytics overview</a>
            <a class="admindash-sentiment-next__link" href="<?php echo (new moodle_url('/local/admindashboard/course_analytics_modules.php')); ?>">Modules report</a>
            <span class="admindash-sentiment-next__link admindash-sentiment-next__link--current" aria-current="page"><?php echo get_string('ui_sentiment_analyzer_sentiment', 'local_admindashboard'); ?></span>
        </div>
    </nav>
</div>

<script>
(function waitForRequire() {
    if (typeof require !== 'function') {
        window.setTimeout(waitForRequire, 50);
        return;
    }
    require(['core/chartjs'], function(Chart) {
let feedbackSentimentChartInstance = null;
let feedbackInsightsState = { loading: false, lastCourseId: 0, requestId: 0 };
const numberFmt = new Intl.NumberFormat('en-US');
const initialCourseId = <?php echo json_encode((int)$courseid); ?>;
const adminDashSesskey = <?php echo json_encode($sesskey); ?>;

function setSentimentLoading(on) {
    const root = document.getElementById('sentimentAnalyzerRoot');
    if (root) {
        root.classList.toggle('is-loading', Boolean(on));
    }
}

function getThemeCssVar(name, fallback) {
    const root = document.body || document.documentElement;
    const value = root ? getComputedStyle(root).getPropertyValue(name).trim() : '';
    return value || fallback;
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
    const flaggedTrack = document.getElementById('feedbackFlaggedCommentsTrack');
    const flaggedViewport = document.getElementById('feedbackFlaggedCommentsViewport');
    if (quantWrap) {
        quantWrap.innerHTML = '';
    }
    if (keywordWrap) {
        keywordWrap.innerHTML = '';
    }
    if (flaggedTrack) {
        flaggedTrack.innerHTML = '';
        flaggedTrack.classList.remove('admindash-sentiment-comments-marquee__track--animated');
        flaggedTrack.style.removeProperty('--marquee-duration');
    }
    if (flaggedViewport) {
        flaggedViewport.style.display = 'none';
    }
}

function resetFeedbackAnalyzer(metaText) {
    const overall = document.getElementById('feedbackOverallAverage');
    const meta = document.getElementById('feedbackAnalyzerMeta');
    const badge = document.getElementById('feedbackAnalyzerBadge');

    clearFeedbackAnalyzerText();
    if (overall) {
        overall.textContent = 'Overall  -  --/5';
    }
    if (meta) {
        meta.textContent = metaText || 'Select a course to load quantitative scores and AI sentiment.';
    }
    if (badge) {
        const inner = '<span class="admindash-sentiment-toolbar__badge-ico" aria-hidden="true">'
            + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M10 2a.75.75 0 0 1 .75.75v1.271a3.375 3.375 0 0 1 2.658 2.658l1.271-.001a.75.75 0 0 1 .498 1.322l-1.272 1.272a3.375 3.375 0 0 1 0 4.756l1.272 1.272a.75.75 0 1 1-1.322.498l-1.271-.001a3.375 3.375 0 0 1-2.658 2.658l.001 1.271a.75.75 0 1 1-1.322 0l-.001-1.271a3.375 3.375 0 0 1-2.658-2.658l-1.271.001a.75.75 0 1 1-.498-1.322l1.272-1.272a3.375 3.375 0 0 1 0-4.756L4.34 7.59a.75.75 0 1 1 .498-1.322l1.271.001a3.375 3.375 0 0 1 2.658-2.658V2.75A.75.75 0 0 1 10 2Z"/></svg></span>';
        if (feedbackInsightsState.loading) {
            badge.innerHTML = inner + 'Analyzing...';
        } else {
            badge.innerHTML = inner + 'Ready';
        }
    }

    const statsEl = document.getElementById('feedbackSentimentStats');
    if (statsEl) {
        statsEl.innerHTML = '';
        statsEl.hidden = true;
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

function renderSentimentSplitStats(split) {
    const statsEl = document.getElementById('feedbackSentimentStats');
    if (!statsEl || !split) {
        if (statsEl) {
            statsEl.innerHTML = '';
            statsEl.hidden = true;
        }
        return;
    }
    const p = Math.round(Number(split.positive_pct || 0));
    const n = Math.round(Number(split.neutral_pct || 0));
    const neg = Math.round(Number(split.negative_pct || 0));
    statsEl.innerHTML = ''
        + '<div class="admindash-sentiment-split-stats__grid" role="list">'
        + '<div class="admindash-sentiment-split-stats__item admindash-sentiment-split-stats__item--pos" role="listitem">'
        + '<span class="admindash-sentiment-split-stats__label">Positive</span>'
        + '<span class="admindash-sentiment-split-stats__value">' + p + '<span class="admindash-sentiment-split-stats__unit">%</span></span></div>'
        + '<div class="admindash-sentiment-split-stats__item admindash-sentiment-split-stats__item--neu" role="listitem">'
        + '<span class="admindash-sentiment-split-stats__label">Neutral</span>'
        + '<span class="admindash-sentiment-split-stats__value">' + n + '<span class="admindash-sentiment-split-stats__unit">%</span></span></div>'
        + '<div class="admindash-sentiment-split-stats__item admindash-sentiment-split-stats__item--neg" role="listitem">'
        + '<span class="admindash-sentiment-split-stats__label">Negative</span>'
        + '<span class="admindash-sentiment-split-stats__value">' + neg + '<span class="admindash-sentiment-split-stats__unit">%</span></span></div>'
        + '</div>';
    statsEl.hidden = false;
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

    const statsEl = document.getElementById('feedbackSentimentStats');
    if (statsEl) {
        statsEl.innerHTML = '';
        statsEl.hidden = true;
    }

    setFeedbackHidden('feedbackSentimentEmpty', hasData);
    if (!hasData) {
        return;
    }

    const ctx = canvas.getContext('2d');
    const w = (canvas && canvas.parentElement) ? canvas.parentElement.offsetWidth : 320;
    const h = 260;
    const ringBorderColor = getThemeCssVar('--ad-feedback-panel-border', 'rgba(148,163,184,.25)');

    const gPos = ctx.createLinearGradient(0, 0, w, h);
    gPos.addColorStop(0, '#4ade80');
    gPos.addColorStop(1, '#059669');

    const gNeu = ctx.createLinearGradient(0, 0, w, h);
    gNeu.addColorStop(0, '#fcd34d');
    gNeu.addColorStop(1, '#d97706');

    const gNeg = ctx.createLinearGradient(0, 0, w, h);
    gNeg.addColorStop(0, '#fb923c');
    gNeg.addColorStop(1, '#dc2626');

    const legendColor = getThemeCssVar('--ad-feedback-label', '#16325c');

    feedbackSentimentChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Positive', 'Neutral', 'Negative'],
            datasets: [{
                data: values,
                backgroundColor: [gPos, gNeu, gNeg],
                borderColor: ringBorderColor,
                borderWidth: 3,
                hoverOffset: 10,
                spacing: 2
            }]
        },
        options: {
            animation: {
                animateRotate: true,
                animateScale: false,
                duration: 640
            },
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: legendColor,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        font: {
                            size: 12,
                            weight: '600'
                        }
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

    renderSentimentSplitStats(split);
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
        overall.textContent = 'Overall  -  --/5';
        setFeedbackHidden('feedbackQuantEmpty', false);
        return;
    }

    setFeedbackHidden('feedbackQuantEmpty', true);
    overall.textContent = 'Overall  -  ' + Number(quantitative.overall_average || 0).toFixed(2) + '/5';

    questions.forEach(function(item) {
        const avg = Number(item.avg_score || 0);
        const max = Number(item.max_score || 5);
        const pct = Math.max(0, Math.min(100, (avg / max) * 100));
        const tone = getFeedbackBarTone(avg);

        const row = document.createElement('div');
        row.className = 'admindash-sentiment-quant-row';

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
        chip.className = 'admindash-feedback__chip admindash-sentiment-chip';
        chip.textContent = String(keyword || '');
        wrap.appendChild(chip);
    });
}

function renderFeedbackFlaggedComments(sentiment) {
    const track = document.getElementById('feedbackFlaggedCommentsTrack');
    const viewport = document.getElementById('feedbackFlaggedCommentsViewport');
    if (!track || !viewport) {
        return;
    }

    track.innerHTML = '';
    track.classList.remove('admindash-sentiment-comments-marquee__track--animated');
    track.style.removeProperty('--marquee-duration');

    const comments = sentiment && Array.isArray(sentiment.flagged_comments) ? sentiment.flagged_comments : [];
    setFeedbackHidden('feedbackFlaggedEmpty', comments.length > 0);

    if (!comments.length) {
        viewport.style.display = 'none';
        return;
    }

    function makeCard(item, index) {
        const card = document.createElement('article');
        const sentimentValue = String(item.sentiment || 'neutral').toLowerCase();
        card.className = 'admindash-feedback__comment admindash-sentiment-comment admindash-sentiment-comment--' + sentimentValue;

        const head = document.createElement('div');
        head.className = 'admindash-feedback__comment-head';

        const title = document.createElement('strong');
        title.className = 'admindash-sentiment-comment__idx';
        title.textContent = 'Comment ' + (index + 1);

        const badge = document.createElement('span');
        badge.className = 'admindash-feedback__sentiment is-' + sentimentValue;
        badge.textContent = sentimentValue;

        head.appendChild(title);
        head.appendChild(badge);

        const text = document.createElement('p');
        text.className = 'admindash-feedback__comment-text';
        text.textContent = String(item.text || '');

        card.appendChild(head);
        card.appendChild(text);
        return card;
    }

    const stripA = document.createElement('div');
    stripA.className = 'admindash-sentiment-comments-marquee__strip';
    comments.forEach(function(item, index) {
        stripA.appendChild(makeCard(item, index));
    });

    const stripB = stripA.cloneNode(true);
    track.appendChild(stripA);
    track.appendChild(stripB);

    viewport.style.display = '';

    let marqueeMeasureAttempts = 0;
    function applyMarqueeDuration() {
        const h = stripA.offsetHeight;
        if (h <= 0 && marqueeMeasureAttempts < 12) {
            marqueeMeasureAttempts += 1;
            window.requestAnimationFrame(applyMarqueeDuration);
            return;
        }
        if (h <= 0) {
            return;
        }
        const pxPerSec = 26;
        const durSec = Math.max(14, Math.min(160, h / pxPerSec));
        track.style.setProperty('--marquee-duration', durSec.toFixed(1) + 's');
        track.classList.add('admindash-sentiment-comments-marquee__track--animated');
    }
    window.requestAnimationFrame(applyMarqueeDuration);
}

function renderFeedbackInsights(payload) {
    const meta = document.getElementById('feedbackAnalyzerMeta');
    const badge = document.getElementById('feedbackAnalyzerBadge');
    const sentiment = payload && payload.sentiment ? payload.sentiment : {};
    const quantitative = payload && payload.quantitative ? payload.quantitative : {};
    const insightMeta = payload && payload.meta ? payload.meta : {};
    const commentsCount = Number(insightMeta.comments_count || 0);
    const hasQuantitative = Boolean(insightMeta.has_quantitative);
    const hasComments = Boolean(insightMeta.has_comments);
    const error = String(sentiment.error || '').trim();

    const badgeInner = '<span class="admindash-sentiment-toolbar__badge-ico" aria-hidden="true">'
        + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M10 2a.75.75 0 0 1 .75.75v1.271a3.375 3.375 0 0 1 2.658 2.658l1.271-.001a.75.75 0 0 1 .498 1.322l-1.272 1.272a3.375 3.375 0 0 1 0 4.756l1.272 1.272a.75.75 0 1 1-1.322.498l-1.271-.001a3.375 3.375 0 0 1-2.658 2.658l.001 1.271a.75.75 0 1 1-1.322 0l-.001-1.271a3.375 3.375 0 0 1-2.658-2.658l-1.271.001a.75.75 0 1 1-.498-1.322l1.272-1.272a3.375 3.375 0 0 1 0-4.756L4.34 7.59a.75.75 0 1 1 .498-1.322l1.271.001a3.375 3.375 0 0 1 2.658-2.658V2.75A.75.75 0 0 1 10 2Z"/></svg></span>';

    if (badge) {
        if (commentsCount > 0) {
            badge.innerHTML = badgeInner + numberFmt.format(commentsCount) + ' comments';
        } else {
            badge.innerHTML = badgeInner + 'Ready';
        }
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
        setSentimentLoading(false);
        resetFeedbackAnalyzer('Select a course to load quantitative scores and AI sentiment.');
        return;
    }

    feedbackInsightsState.loading = true;
    feedbackInsightsState.lastCourseId = courseIdNumber;
    feedbackInsightsState.requestId += 1;
    const requestId = feedbackInsightsState.requestId;
    setSentimentLoading(true);
    resetFeedbackAnalyzer('Loading feedback and running sentiment analysis...');

    const url = new URL('data.php', window.location.href);
    url.searchParams.set('sesskey', adminDashSesskey);
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
        renderFeedbackInsights(payload || {});
    } catch (error) {
        if (requestId !== feedbackInsightsState.requestId) {
            return;
        }
        resetFeedbackAnalyzer('Feedback insights could not be loaded right now.');
    } finally {
        if (requestId === feedbackInsightsState.requestId) {
            feedbackInsightsState.loading = false;
            setSentimentLoading(false);
        }
    }
}

window.addEventListener('load', function() {
    loadFeedbackInsights(initialCourseId);
});
});
})();
</script>

<?php
local_admindashboard_render_footer();
