-- Verify categories update
USE servicelink;
SELECT id, slug, name, icon, sort_order FROM categories ORDER BY sort_order;
