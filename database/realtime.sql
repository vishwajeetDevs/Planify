-- Planify live sync: change log + triggers.
-- Any insert/update/delete on shared data writes one row here.
-- The stream endpoint pushes those rows to other signed-in users.

CREATE TABLE IF NOT EXISTS realtime_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id INT NULL,
    workspace_id INT NULL,
    card_id INT NULL,
    target_user_id INT NULL,
    actor_id INT NULL,
    entity_type VARCHAR(32) NOT NULL,
    entity_id INT NULL,
    action VARCHAR(16) NOT NULL,
    summary VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rt_board (board_id, id),
    KEY idx_rt_workspace (workspace_id, id),
    KEY idx_rt_target (target_user_id, id)
) ENGINE=InnoDB;

DROP TRIGGER IF EXISTS rt_workspaces_ins;
DROP TRIGGER IF EXISTS rt_workspaces_upd;
DROP TRIGGER IF EXISTS rt_workspaces_del;
DROP TRIGGER IF EXISTS rt_workspace_members_ins;
DROP TRIGGER IF EXISTS rt_workspace_members_upd;
DROP TRIGGER IF EXISTS rt_workspace_members_del;
DROP TRIGGER IF EXISTS rt_boards_ins;
DROP TRIGGER IF EXISTS rt_boards_upd;
DROP TRIGGER IF EXISTS rt_boards_del;
DROP TRIGGER IF EXISTS rt_board_members_ins;
DROP TRIGGER IF EXISTS rt_board_members_upd;
DROP TRIGGER IF EXISTS rt_board_members_del;
DROP TRIGGER IF EXISTS rt_lists_ins;
DROP TRIGGER IF EXISTS rt_lists_upd;
DROP TRIGGER IF EXISTS rt_lists_del;
DROP TRIGGER IF EXISTS rt_cards_ins;
DROP TRIGGER IF EXISTS rt_cards_upd;
DROP TRIGGER IF EXISTS rt_cards_del;
DROP TRIGGER IF EXISTS rt_card_assignees_ins;
DROP TRIGGER IF EXISTS rt_card_assignees_del;
DROP TRIGGER IF EXISTS rt_labels_ins;
DROP TRIGGER IF EXISTS rt_labels_upd;
DROP TRIGGER IF EXISTS rt_labels_del;
DROP TRIGGER IF EXISTS rt_card_labels_ins;
DROP TRIGGER IF EXISTS rt_card_labels_del;
DROP TRIGGER IF EXISTS rt_checklists_ins;
DROP TRIGGER IF EXISTS rt_checklists_upd;
DROP TRIGGER IF EXISTS rt_checklists_del;
DROP TRIGGER IF EXISTS rt_checklist_items_ins;
DROP TRIGGER IF EXISTS rt_checklist_items_upd;
DROP TRIGGER IF EXISTS rt_checklist_items_del;
DROP TRIGGER IF EXISTS rt_comments_ins;
DROP TRIGGER IF EXISTS rt_comments_upd;
DROP TRIGGER IF EXISTS rt_comments_del;
DROP TRIGGER IF EXISTS rt_attachments_ins;
DROP TRIGGER IF EXISTS rt_attachments_del;
DROP TRIGGER IF EXISTS rt_share_links_ins;
DROP TRIGGER IF EXISTS rt_share_links_upd;
DROP TRIGGER IF EXISTS rt_share_links_del;
DROP TRIGGER IF EXISTS rt_join_requests_ins;
DROP TRIGGER IF EXISTS rt_join_requests_upd;
DROP TRIGGER IF EXISTS rt_join_requests_del;
DROP TRIGGER IF EXISTS rt_notifications_ins;
DROP TRIGGER IF EXISTS rt_notifications_upd;

DELIMITER $$

CREATE TRIGGER rt_workspaces_ins AFTER INSERT ON workspaces
FOR EACH ROW
INSERT INTO realtime_events (workspace_id, actor_id, entity_type, entity_id, action, summary)
VALUES (NEW.id, @planify_user_id, 'workspace', NEW.id, 'created', NEW.name)$$

CREATE TRIGGER rt_workspaces_upd AFTER UPDATE ON workspaces
FOR EACH ROW
INSERT INTO realtime_events (workspace_id, actor_id, entity_type, entity_id, action, summary)
VALUES (NEW.id, @planify_user_id, 'workspace', NEW.id, 'updated', NEW.name)$$

CREATE TRIGGER rt_workspaces_del AFTER DELETE ON workspaces
FOR EACH ROW
INSERT INTO realtime_events (workspace_id, actor_id, entity_type, entity_id, action, summary)
VALUES (OLD.id, @planify_user_id, 'workspace', OLD.id, 'deleted', OLD.name)$$

CREATE TRIGGER rt_workspace_members_ins AFTER INSERT ON workspace_members
FOR EACH ROW
INSERT INTO realtime_events (workspace_id, target_user_id, actor_id, entity_type, entity_id, action)
VALUES (NEW.workspace_id, NEW.user_id, @planify_user_id, 'workspace_member', NEW.user_id, 'created')$$

CREATE TRIGGER rt_workspace_members_upd AFTER UPDATE ON workspace_members
FOR EACH ROW
INSERT INTO realtime_events (workspace_id, target_user_id, actor_id, entity_type, entity_id, action)
VALUES (NEW.workspace_id, NEW.user_id, @planify_user_id, 'workspace_member', NEW.user_id, 'updated')$$

CREATE TRIGGER rt_workspace_members_del AFTER DELETE ON workspace_members
FOR EACH ROW
INSERT INTO realtime_events (workspace_id, target_user_id, actor_id, entity_type, entity_id, action)
VALUES (OLD.workspace_id, OLD.user_id, @planify_user_id, 'workspace_member', OLD.user_id, 'deleted')$$

CREATE TRIGGER rt_boards_ins AFTER INSERT ON boards
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, actor_id, entity_type, entity_id, action, summary)
VALUES (NEW.id, NEW.workspace_id, @planify_user_id, 'board', NEW.id, 'created', NEW.name)$$

CREATE TRIGGER rt_boards_upd AFTER UPDATE ON boards
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, actor_id, entity_type, entity_id, action, summary)
VALUES (NEW.id, NEW.workspace_id, @planify_user_id, 'board', NEW.id, 'updated', NEW.name)$$

CREATE TRIGGER rt_boards_del AFTER DELETE ON boards
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, actor_id, entity_type, entity_id, action, summary)
VALUES (OLD.id, OLD.workspace_id, @planify_user_id, 'board', OLD.id, 'deleted', OLD.name)$$

CREATE TRIGGER rt_board_members_ins AFTER INSERT ON board_members
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, target_user_id, actor_id, entity_type, entity_id, action)
SELECT NEW.board_id, b.workspace_id, NEW.user_id, @planify_user_id, 'board_member', NEW.user_id, 'created'
FROM boards b WHERE b.id = NEW.board_id$$

CREATE TRIGGER rt_board_members_upd AFTER UPDATE ON board_members
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, target_user_id, actor_id, entity_type, entity_id, action)
SELECT NEW.board_id, b.workspace_id, NEW.user_id, @planify_user_id, 'board_member', NEW.user_id, 'updated'
FROM boards b WHERE b.id = NEW.board_id$$

CREATE TRIGGER rt_board_members_del AFTER DELETE ON board_members
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, target_user_id, actor_id, entity_type, entity_id, action)
SELECT OLD.board_id, b.workspace_id, OLD.user_id, @planify_user_id, 'board_member', OLD.user_id, 'deleted'
FROM boards b WHERE b.id = OLD.board_id$$

CREATE TRIGGER rt_lists_ins AFTER INSERT ON lists
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, actor_id, entity_type, entity_id, action, summary)
SELECT NEW.board_id, b.workspace_id, @planify_user_id, 'list', NEW.id, 'created', NEW.title
FROM boards b WHERE b.id = NEW.board_id$$

CREATE TRIGGER rt_lists_upd AFTER UPDATE ON lists
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, actor_id, entity_type, entity_id, action, summary)
SELECT NEW.board_id, b.workspace_id, @planify_user_id, 'list', NEW.id, 'updated', NEW.title
FROM boards b WHERE b.id = NEW.board_id$$

CREATE TRIGGER rt_lists_del AFTER DELETE ON lists
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, actor_id, entity_type, entity_id, action, summary)
SELECT OLD.board_id, b.workspace_id, @planify_user_id, 'list', OLD.id, 'deleted', OLD.title
FROM boards b WHERE b.id = OLD.board_id$$

CREATE TRIGGER rt_cards_ins AFTER INSERT ON cards
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action, summary)
SELECT l.board_id, NEW.id, @planify_user_id, 'card', NEW.id, 'created', NEW.title
FROM lists l WHERE l.id = NEW.list_id$$

CREATE TRIGGER rt_cards_upd AFTER UPDATE ON cards
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action, summary)
SELECT l.board_id, NEW.id, @planify_user_id, 'card', NEW.id, 'updated', NEW.title
FROM lists l WHERE l.id = NEW.list_id$$

CREATE TRIGGER rt_cards_del AFTER DELETE ON cards
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action, summary)
SELECT l.board_id, OLD.id, @planify_user_id, 'card', OLD.id, 'deleted', OLD.title
FROM lists l WHERE l.id = OLD.list_id$$

CREATE TRIGGER rt_card_assignees_ins AFTER INSERT ON card_assignees
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, target_user_id, actor_id, entity_type, entity_id, action)
SELECT l.board_id, NEW.card_id, NEW.user_id, @planify_user_id, 'assignee', NEW.user_id, 'created'
FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = NEW.card_id$$

CREATE TRIGGER rt_card_assignees_del AFTER DELETE ON card_assignees
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, target_user_id, actor_id, entity_type, entity_id, action)
SELECT l.board_id, OLD.card_id, OLD.user_id, @planify_user_id, 'assignee', OLD.user_id, 'deleted'
FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = OLD.card_id$$

CREATE TRIGGER rt_labels_ins AFTER INSERT ON labels
FOR EACH ROW
INSERT INTO realtime_events (board_id, actor_id, entity_type, entity_id, action, summary)
VALUES (NEW.board_id, @planify_user_id, 'label', NEW.id, 'created', NEW.name)$$

CREATE TRIGGER rt_labels_upd AFTER UPDATE ON labels
FOR EACH ROW
INSERT INTO realtime_events (board_id, actor_id, entity_type, entity_id, action, summary)
VALUES (NEW.board_id, @planify_user_id, 'label', NEW.id, 'updated', NEW.name)$$

CREATE TRIGGER rt_labels_del AFTER DELETE ON labels
FOR EACH ROW
INSERT INTO realtime_events (board_id, actor_id, entity_type, entity_id, action, summary)
VALUES (OLD.board_id, @planify_user_id, 'label', OLD.id, 'deleted', OLD.name)$$

CREATE TRIGGER rt_card_labels_ins AFTER INSERT ON card_labels
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action)
SELECT l.board_id, NEW.card_id, @planify_user_id, 'card_label', NEW.label_id, 'created'
FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = NEW.card_id$$

CREATE TRIGGER rt_card_labels_del AFTER DELETE ON card_labels
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action)
SELECT l.board_id, OLD.card_id, @planify_user_id, 'card_label', OLD.label_id, 'deleted'
FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = OLD.card_id$$

CREATE TRIGGER rt_checklists_ins AFTER INSERT ON checklists
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action, summary)
SELECT l.board_id, NEW.card_id, @planify_user_id, 'checklist', NEW.id, 'created', NEW.title
FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = NEW.card_id$$

CREATE TRIGGER rt_checklists_upd AFTER UPDATE ON checklists
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action, summary)
SELECT l.board_id, NEW.card_id, @planify_user_id, 'checklist', NEW.id, 'updated', NEW.title
FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = NEW.card_id$$

CREATE TRIGGER rt_checklists_del AFTER DELETE ON checklists
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action, summary)
SELECT l.board_id, OLD.card_id, @planify_user_id, 'checklist', OLD.id, 'deleted', OLD.title
FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = OLD.card_id$$

CREATE TRIGGER rt_checklist_items_ins AFTER INSERT ON checklist_items
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action, summary)
SELECT l.board_id, cl.card_id, @planify_user_id, 'checklist_item', NEW.id, 'created', NEW.title
FROM checklists cl JOIN cards c ON c.id = cl.card_id JOIN lists l ON l.id = c.list_id
WHERE cl.id = NEW.checklist_id$$

CREATE TRIGGER rt_checklist_items_upd AFTER UPDATE ON checklist_items
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action, summary)
SELECT l.board_id, cl.card_id, @planify_user_id, 'checklist_item', NEW.id, 'updated', NEW.title
FROM checklists cl JOIN cards c ON c.id = cl.card_id JOIN lists l ON l.id = c.list_id
WHERE cl.id = NEW.checklist_id$$

CREATE TRIGGER rt_checklist_items_del AFTER DELETE ON checklist_items
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action, summary)
SELECT l.board_id, cl.card_id, @planify_user_id, 'checklist_item', OLD.id, 'deleted', OLD.title
FROM checklists cl JOIN cards c ON c.id = cl.card_id JOIN lists l ON l.id = c.list_id
WHERE cl.id = OLD.checklist_id$$

CREATE TRIGGER rt_comments_ins AFTER INSERT ON comments
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action)
SELECT l.board_id, NEW.card_id, @planify_user_id, 'comment', NEW.id, 'created'
FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = NEW.card_id$$

CREATE TRIGGER rt_comments_upd AFTER UPDATE ON comments
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action)
SELECT l.board_id, NEW.card_id, @planify_user_id, 'comment', NEW.id, 'updated'
FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = NEW.card_id$$

CREATE TRIGGER rt_comments_del AFTER DELETE ON comments
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action)
SELECT l.board_id, OLD.card_id, @planify_user_id, 'comment', OLD.id, 'deleted'
FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = OLD.card_id$$

CREATE TRIGGER rt_attachments_ins AFTER INSERT ON attachments
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action, summary)
SELECT l.board_id, NEW.card_id, @planify_user_id, 'attachment', NEW.id, 'created', NEW.original_name
FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = NEW.card_id$$

CREATE TRIGGER rt_attachments_del AFTER DELETE ON attachments
FOR EACH ROW
INSERT INTO realtime_events (board_id, card_id, actor_id, entity_type, entity_id, action, summary)
SELECT l.board_id, OLD.card_id, @planify_user_id, 'attachment', OLD.id, 'deleted', OLD.original_name
FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = OLD.card_id$$

CREATE TRIGGER rt_share_links_ins AFTER INSERT ON share_links
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, actor_id, entity_type, entity_id, action)
SELECT NEW.board_id, b.workspace_id, @planify_user_id, 'share_link', NEW.id, 'created'
FROM boards b WHERE b.id = NEW.board_id$$

CREATE TRIGGER rt_share_links_upd AFTER UPDATE ON share_links
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, actor_id, entity_type, entity_id, action)
SELECT NEW.board_id, b.workspace_id, @planify_user_id, 'share_link', NEW.id, 'updated'
FROM boards b WHERE b.id = NEW.board_id$$

CREATE TRIGGER rt_share_links_del AFTER DELETE ON share_links
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, actor_id, entity_type, entity_id, action)
SELECT OLD.board_id, b.workspace_id, @planify_user_id, 'share_link', OLD.id, 'deleted'
FROM boards b WHERE b.id = OLD.board_id$$

CREATE TRIGGER rt_join_requests_ins AFTER INSERT ON join_requests
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, target_user_id, actor_id, entity_type, entity_id, action)
SELECT NEW.board_id, b.workspace_id, NEW.user_id, @planify_user_id, 'join_request', NEW.id, 'created'
FROM boards b WHERE b.id = NEW.board_id$$

CREATE TRIGGER rt_join_requests_upd AFTER UPDATE ON join_requests
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, target_user_id, actor_id, entity_type, entity_id, action)
SELECT NEW.board_id, b.workspace_id, NEW.user_id, @planify_user_id, 'join_request', NEW.id, 'updated'
FROM boards b WHERE b.id = NEW.board_id$$

CREATE TRIGGER rt_join_requests_del AFTER DELETE ON join_requests
FOR EACH ROW
INSERT INTO realtime_events (board_id, workspace_id, target_user_id, actor_id, entity_type, entity_id, action)
SELECT OLD.board_id, b.workspace_id, OLD.user_id, @planify_user_id, 'join_request', OLD.id, 'deleted'
FROM boards b WHERE b.id = OLD.board_id$$

CREATE TRIGGER rt_notifications_ins AFTER INSERT ON notifications
FOR EACH ROW
INSERT INTO realtime_events (target_user_id, actor_id, entity_type, entity_id, action, summary)
VALUES (NEW.user_id, @planify_user_id, 'notification', NEW.id, 'created', NEW.title)$$

CREATE TRIGGER rt_notifications_upd AFTER UPDATE ON notifications
FOR EACH ROW
INSERT INTO realtime_events (target_user_id, actor_id, entity_type, entity_id, action, summary)
VALUES (NEW.user_id, @planify_user_id, 'notification', NEW.id, 'updated', NEW.title)$$

DELIMITER ;
