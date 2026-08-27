# 🐛 Bug Registry: Dashboard Syntax Error & Hash Reset

## 1. Issue Description
On `app/dashboard.php`, clicking buttons (Assign Driver, Set Status, Boost Fare) occasionally froze or threw `SyntaxError: Unexpected token '<'` in the browser console.

## 2. Root Cause
- A missing `</script>` closing tag right above `<!-- RIDE ISSUE REPORTS DESK MODAL -->` caused HTML markup to be parsed as JavaScript.
- `lastKnownDataHash` in `fetchLiveUpdates()` was comparing empty hashes on manual clicks without forcing a DOM re-render.

## 3. Solution Applied
1. Closed the `<script>` tag properly before `#reports-desk-modal`.
2. Updated `triggerManualSync()` to explicitly reset `lastKnownDataHash = ''`, guaranteeing an instant DOM re-render upon driver assignment or fare edits.

## 4. Prevention Rule
- Always check that `<script>` and `</script>` tags enclose only valid JavaScript code and never bleed into HTML comments or modal templates.
