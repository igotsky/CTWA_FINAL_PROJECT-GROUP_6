<?php
// includes/eligibility.php
// Eligibility helpers for organization applications.

function getOrgAllowedCourses($conn, $orgId) {
    // Ensure table exists (self-healing)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS org_allowed_courses (
        organization_id INT NOT NULL,
        course VARCHAR(80) NOT NULL,
        PRIMARY KEY (organization_id, course),
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
    )");
    $courses = [];
    $stmt = mysqli_prepare($conn, 'SELECT course FROM org_allowed_courses WHERE organization_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $orgId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) { $courses[] = $row['course']; }
    mysqli_stmt_close($stmt);
    return $courses;
}

function countApprovedMembers($conn, $orgId) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM memberships WHERE organization_id = ? AND status = 'Approved'");
    mysqli_stmt_bind_param($stmt, 'i', $orgId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return (int) ($row['c'] ?? 0);
}

// Returns array of reason strings; empty array = eligible.
function checkEligibility($conn, $student, $org) {
    $reasons = [];

    if ($org['min_year_level'] !== null && (int)$student['year_level'] < (int)$org['min_year_level']) {
        $reasons[] = 'Year level must be at least ' . (int)$org['min_year_level'];
    }
    if ($org['max_year_level'] !== null && (int)$student['year_level'] > (int)$org['max_year_level']) {
        $reasons[] = 'Year level must be at most ' . (int)$org['max_year_level'];
    }
    if (!empty($org['allowed_gender']) && $org['allowed_gender'] !== 'Any' && $student['gender'] !== $org['allowed_gender']) {
        $reasons[] = 'Only ' . $org['allowed_gender'] . ' students may apply';
    }

    $allowedCourses = getOrgAllowedCourses($conn, (int)$org['id']);
    if (!empty($allowedCourses) && !in_array($student['course'], $allowedCourses, true)) {
        $reasons[] = 'Your course is not allowed';
    }

    if (!empty($org['application_deadline']) && date('Y-m-d') > $org['application_deadline']) {
        $reasons[] = 'Application deadline has passed';
    }

    if ($org['max_members'] !== null) {
        $approved = countApprovedMembers($conn, (int)$org['id']);
        if ($approved >= (int)$org['max_members']) {
            $reasons[] = 'Organization is full (' . $approved . '/' . (int)$org['max_members'] . ')';
        }
    }

    return $reasons;
}