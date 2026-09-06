WITH studentDepts AS (
	SELECT s.StudentID, d.DeptName
    FROM Student s
    LEFT JOIN StudentMajor sm ON s.StudentID = sm.StudentID
    LEFT JOIN Major m on sm.MajorID = sm.MajorID
    LEFT JOIN Department d ON m.DeptID = d.DeptID
),

studentRankings AS (
	SELECT sd.StudentID, DeptName, CumulativeGPA, ROW_NUMBER() OVER (PARTITION BY sd.DeptName ORDER BY da.CumulativeGPA DESC) AS ranking
    FROM studentDepts sd
    LEFT JOIN DegreeAudit da ON sd.StudentID = da.StudentID
)

SELECT StudentID, DeptName, CumulativeGPA, ranking
FROM studentRankings
WHERE ranking <=3