# Remove a student

Super admins and admissions staff can use **Admin portal > Students > Remove**, or **View > Remove student**. The confirmation identifies the student's name and applicant number and explains that removal is permanent. Cancel keeps the record. Existing account archiving remains available through View > Account status.

Removal deletes the account and linked exam sessions, answers, results, recommendations, notifications and trusted devices. It revokes authentication tokens and removes password-reset and OTP records for the student's email. Uploaded profile pictures are cleaned up after the database transaction commits; external Google profile images are not touched. Shared question-bank and program records remain intact.

The database deletion and `student.deleted` admin activity record are one transaction. The activity record retains the acting admin, student ID, name, email, applicant number and number of removed results. Historical student activity records retain their history with a null student ID, consistent with the existing database schema.

## API

`DELETE /api/admin/students/{student}` requires an authenticated active `super_admin` or `admissions_staff` account. It returns `200 {success: true, message: "Student account and linked examination records removed permanently."}`. A student or admin without student-management permission receives `403`; an unknown or already removed student receives `404` for an authorized caller.

The endpoint locks the student using the same lock as exam-session writes. If database deletion or audit logging fails, database changes and token revocation roll back. No migration is required.

Deleting an account does not blacklist its email from future registration or Google sign-in. Use suspension or archiving when the account should remain in the system with access disabled.

Verification uses disposable students in an isolated in-memory database and browser fixtures. No existing student was removed while implementing this feature.
