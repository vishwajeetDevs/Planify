-- Keep only Super Admin (owner), Admin (admin), and Member (member).
-- Existing Commentator and Viewer rows become Member.
-- Run this on an existing Planify database. Fresh installs use database.sql.

UPDATE board_members SET role = 'member' WHERE role IN ('commenter', 'viewer');
UPDATE workspace_members SET role = 'member' WHERE role IN ('viewer', 'commenter');
UPDATE share_links SET role_on_join = 'member' WHERE role_on_join IN ('viewer', 'commenter');

ALTER TABLE workspace_members
    MODIFY role ENUM('owner', 'admin', 'member') NULL DEFAULT 'member';

ALTER TABLE board_members
    MODIFY role ENUM('owner', 'admin', 'member') NULL DEFAULT 'member';

ALTER TABLE share_links
    MODIFY role_on_join ENUM('admin', 'member') NULL DEFAULT 'member';
