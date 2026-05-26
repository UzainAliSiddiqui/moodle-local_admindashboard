<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

admindash_setup_page('/local/admindashboard/skill_gap_matrix.php', 'Skill Gap Matrix', 'skills.gap');
admindash_render_header('skills.gap');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$meta = admindash_get_meta($courseid);
$tabs = admindash_get_skill_certifications_suite_tabs();

[$userwhere, $userparams] = admindash_build_user_filter($department);
$courseconditions = ['c.id > 1', 'c.visible = 1'];
$courseparams = $userparams;
if ($courseid > 0) {
	$courseconditions[] = 'c.id = :matrixcourseid';
	$courseparams['matrixcourseid'] = $courseid;
}
$coursewhere = implode(' AND ', $courseconditions);

$certunion = admindash_get_certificate_issue_union_sql();
$certcoursejoin = '';
$certuserjoin = '';
$certcoursecountexpr = '0';
$deptcertcountexpr = '0';
if ($certunion['available']) {
	$certcoursejoin = "LEFT JOIN (
			SELECT cert.userid, cert.courseid, MAX(cert.issuedat) AS latestissuedat
			  FROM ({$certunion['sql']}) cert
		  GROUP BY cert.userid, cert.courseid
	   ) cert ON cert.userid = u.id AND cert.courseid = c.id";
	$certuserjoin = "LEFT JOIN (
			SELECT DISTINCT cert.userid
			  FROM ({$certunion['sql']}) cert
	   ) certusers ON certusers.userid = u.id";
	 $certcoursecountexpr = 'COUNT(DISTINCT CASE WHEN cert.userid IS NOT NULL THEN u.id END)';
	 $deptcertcountexpr = 'COUNT(DISTINCT certusers.userid)';
}

$competencyavailable = false;
$frameworkcount = 0;
$competencycount = 0;
$competencyrecords = 0;
$proficiencyrecords = 0;
$departmentgaprows = [];

require_once($CFG->libdir . '/xmldb/xmldb_table.php');
$manager = $DB->get_manager();
$coursecompetencyjoin = '';
$coursecompetencyselect = '0';
$coursegroupbyextra = '';
if ($manager->table_exists(new xmldb_table('competency_coursecomp'))) {
	$coursecompetencyjoin = "LEFT JOIN (
			SELECT courseid, COUNT(DISTINCT competencyid) AS competencycount
			  FROM {competency_coursecomp}
		  GROUP BY courseid
	   ) compmap ON compmap.courseid = c.id";
	$coursecompetencyselect = 'COALESCE(compmap.competencycount, 0)';
	$coursegroupbyextra = ', compmap.competencycount';
}

if ($manager->table_exists(new xmldb_table('competency_framework'))
		&& $manager->table_exists(new xmldb_table('competency'))
		&& $manager->table_exists(new xmldb_table('competency_usercomp'))) {
	$competencyavailable = true;
	$frameworkcount = (int)$DB->count_records('competency_framework');
	$competencycount = (int)$DB->count_records('competency');
	$compsummary = $DB->get_record_sql(
		"SELECT COUNT(1) AS totalrecords,
				SUM(CASE WHEN uc.proficiency = 1 THEN 1 ELSE 0 END) AS proficientrecords,
				COUNT(DISTINCT uc.userid) AS userscovered
		   FROM {competency_usercomp} uc
		   JOIN {user} u ON u.id = uc.userid
		  WHERE {$userwhere}",
		$userparams
	);
	$competencyrecords = (int)($compsummary->totalrecords ?? 0);
	$proficiencyrecords = (int)($compsummary->proficientrecords ?? 0);

	$departmentgaprows = $DB->get_records_sql(
		"SELECT COALESCE(NULLIF(u.department, ''), 'Unassigned') AS departmentlabel,
				COUNT(DISTINCT uc.userid) AS learnercount,
				SUM(CASE WHEN uc.proficiency = 1 THEN 1 ELSE 0 END) AS proficientcount,
				SUM(CASE WHEN uc.proficiency = 0 OR uc.proficiency IS NULL THEN 1 ELSE 0 END) AS gapcount
		   FROM {competency_usercomp} uc
		   JOIN {user} u ON u.id = uc.userid
		  WHERE {$userwhere}
	   GROUP BY COALESCE(NULLIF(u.department, ''), 'Unassigned')
	   ORDER BY gapcount DESC, learnercount DESC, departmentlabel ASC",
		$userparams,
		0,
		8
	);
}

$coursegaprows = $DB->get_records_sql(
	"SELECT c.id, c.fullname,
			COUNT(DISTINCT u.id) AS learnercount,
			COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL AND cc.timecompleted > 0 THEN u.id END) AS completedcount,
						{$certcoursecountexpr} AS certifiedcount,
						{$coursecompetencyselect} AS competencycount
	   FROM {course} c
  LEFT JOIN {enrol} e ON e.courseid = c.id AND e.status = 0
  LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
  LEFT JOIN {user} u ON u.id = ue.userid
  LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
  {$certcoursejoin}
	{$coursecompetencyjoin}
	  WHERE {$coursewhere}
		AND {$userwhere}
	GROUP BY c.id, c.fullname{$coursegroupbyextra}
   ORDER BY learnercount DESC, completedcount DESC, c.fullname ASC",
	$courseparams,
	0,
	12
);

$skillproxyrows = [];
foreach ($coursegaprows as $row) {
	$coverage = max((int)$row->completedcount, (int)$row->certifiedcount);
	$gap = max(0, (int)$row->learnercount - $coverage);
	$readinesspct = (int)$row->learnercount > 0 ? (int)round(($coverage / (int)$row->learnercount) * 100) : 0;
	$skillproxyrows[] = [
		'id' => (int)$row->id,
		'fullname' => (string)$row->fullname,
		'learners' => (int)$row->learnercount,
		'completed' => (int)$row->completedcount,
		'certified' => (int)$row->certifiedcount,
		'competencies' => (int)$row->competencycount,
		'gap' => $gap,
		'readiness' => $readinesspct,
	];
}

$deptproxyrows = [];
if (!$competencyavailable) {
	$deptproxyrows = $DB->get_records_sql(
		"SELECT COALESCE(NULLIF(u.department, ''), 'Unassigned') AS departmentlabel,
				COUNT(DISTINCT u.id) AS learnercount,
				COUNT(DISTINCT compusers.userid) AS completedusers,
				{$deptcertcountexpr} AS certifiedusers
		   FROM {user} u
		   JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
		   JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
		   JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
	  LEFT JOIN (
				SELECT DISTINCT cc.userid
				  FROM {course_completions} cc
				  JOIN {course} c2 ON c2.id = cc.course AND c2.visible = 1 AND c2.id > 1
				 WHERE cc.timecompleted IS NOT NULL AND cc.timecompleted > 0
		   ) compusers ON compusers.userid = u.id
		   {$certuserjoin}
		  WHERE {$userwhere}
	   GROUP BY COALESCE(NULLIF(u.department, ''), 'Unassigned')
	   ORDER BY learnercount DESC, departmentlabel ASC",
		$userparams,
		0,
		8
	);
}

$totaltracks = count($skillproxyrows);
$totallearners = 0;
$totalcoverage = 0;
$totalcertified = 0;
foreach ($skillproxyrows as $row) {
	$totallearners += $row['learners'];
	$totalcoverage += max($row['completed'], $row['certified']);
	$totalcertified += $row['certified'];
}
$overallgap = max(0, $totallearners - $totalcoverage);

admindash_render_workspace_header(
	'Reports & Analytics / Skill Gap & Certifications',
	'Skill Gap Matrix',
	'Competency-informed view of where learner capability is covered, certified, or still lagging behind the required training footprint.',
	'certification',
	'skills.gap',
	$tabs,
	[
		['label' => 'Certificate status', 'url' => new moodle_url('/local/admindashboard/certificate_status.php'), 'primary' => true],
		['label' => 'Renewal readiness', 'url' => new moodle_url('/local/admindashboard/renewal_readiness.php'), 'primary' => false],
		['label' => 'Course analytics', 'url' => new moodle_url('/local/admindashboard/course_analytics.php'), 'primary' => false],
	],
	[
		$competencyavailable ? 'Competency backed' : 'Course-skill proxy',
		$certunion['available'] ? 'Certification aware' : 'No certificate plugin detected',
		'Department scoped',
	]
);
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title">Filters</div>

	<label class="mb-0" for="courseSelect">Course</label>
	<select id="courseSelect" name="courseid" class="form-select" style="max-width:320px">
		<option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>All Courses</option>
		<?php foreach ($meta['courses'] as $course): ?>
			<option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
				<?php echo s($course['fullname']); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="deptSelect">Department</label>
	<select id="deptSelect" name="department" class="form-select" style="max-width:320px">
		<option value="" <?php echo $department === '' ? 'selected' : ''; ?>>All Departments</option>
		<?php foreach ($meta['departments'] as $dept): ?>
			<option value="<?php echo s($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
				<?php echo s($dept); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/skill_gap_matrix.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Tracks in View</div>
		<div class="admindash-module-stat__value"><?php echo $totaltracks; ?></div>
		<div class="admindash-module-stat__meta">Courses currently acting as skill-track proxies inside the selected scope.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Certified Coverage</div>
		<div class="admindash-module-stat__value"><?php echo $totalcertified; ?></div>
		<div class="admindash-module-stat__meta">Certification-backed learner coverage across the visible tracks.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Gap Proxy</div>
		<div class="admindash-module-stat__value"><?php echo $overallgap; ?></div>
		<div class="admindash-module-stat__meta">Learner volume not yet covered by completion or certification signals.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo $competencyavailable ? 'Competencies' : 'Framework Signals'; ?></div>
		<div class="admindash-module-stat__value"><?php echo $competencyavailable ? $competencycount : count($skillproxyrows); ?></div>
		<div class="admindash-module-stat__meta"><?php echo $competencyavailable ? ($frameworkcount . ' frameworks and ' . $competencyrecords . ' learner competency records detected.') : 'Competency tables not detected, so this page is using course and certificate signals.'; ?></div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo $competencyavailable ? 'Department Skill Gaps' : 'Department Coverage Proxy'; ?></h5>
			<span class="admindash-admin-note">Top departments</span>
		</div>
		<?php $gaprows = $competencyavailable ? $departmentgaprows : $deptproxyrows; ?>
		<?php if (!empty($gaprows)): ?>
			<ul class="admindash-admin-list">
				<?php foreach ($gaprows as $row): ?>
					<?php
					$label = $competencyavailable
						? ((int)$row->proficientcount . ' proficient / ' . (int)$row->gapcount . ' gap records')
						: ((int)$row->completedusers . ' completed / ' . (int)$row->certifiedusers . ' certified');
					?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($row->departmentlabel); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$row->learnercount; ?> learners · <?php echo s($label); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<p class="admindash-admin-note mb-0">No department-level gap data was returned for the current filters.</p>
		<?php endif; ?>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1">Skill Track Matrix</h5>
			<p class="admindash-admin-note mb-0">This table uses visible courses as the skill-track layer, then overlays completion, certificate, and competency signals.</p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th>Track</th>
					<th>Learners</th>
					<th>Completed</th>
					<th>Certified</th>
					<th>Competencies</th>
					<th>Gap</th>
					<th>Readiness</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($skillproxyrows)): ?>
					<tr>
						<td colspan="8" class="text-center py-4">No skill-track rows were found for the current scope.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($skillproxyrows as $row): ?>
						<?php
						$signals = [];
						if ($row['gap'] > 0) {
							$signals[] = '<span class="admindash-admin-badge is-danger">' . $row['gap'] . ' gap</span>';
						}
						if ($row['certified'] > 0) {
							$signals[] = '<span class="admindash-admin-badge is-success">Certified path</span>';
						}
						if ($row['competencies'] > 0) {
							$signals[] = '<span class="admindash-admin-badge is-info">Competency linked</span>';
						}
						?>
						<tr>
							<td>
								<div class="admindash-admin-user">
									<a href="<?php echo new moodle_url('/course/view.php', ['id' => $row['id']]); ?>" class="admindash-admin-user__name"><?php echo s($row['fullname']); ?></a>
									<div class="admindash-admin-note">Course-backed skill track</div>
								</div>
							</td>
							<td><?php echo $row['learners']; ?></td>
							<td><?php echo $row['completed']; ?></td>
							<td><?php echo $row['certified']; ?></td>
							<td><?php echo $row['competencies']; ?></td>
							<td><?php echo $row['gap']; ?></td>
							<td><?php echo $row['readiness']; ?>%</td>
							<td>
								<div class="admindash-admin-actions-inline">
									<a href="<?php echo new moodle_url('/course/view.php', ['id' => $row['id']]); ?>">Open</a>
									<a href="<?php echo new moodle_url('/local/admindashboard/certificate_status.php', ['courseid' => $row['id']]); ?>">Certificates</a>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php
admindash_render_footer();