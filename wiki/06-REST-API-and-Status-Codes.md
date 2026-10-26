# 06 REST API and HTTP status codes

**Goal:** the same routes serve a JSON API with correct status codes, ready for the Vue front end of Part 2.

## Manual
`Auth::activateAPI();` (first line in `web.php`) lets JSON clients in with the `API_KEY` from `.env`:
```bash
K=<your API_KEY>
H=(-H "Accept: application/json" -H "Authorization: Bearer $K")
curl "${H[@]}" http://localhost:8080/projects                                   # 200 {"projects":[...]}
curl "${H[@]}" -H "Content-Type: application/json" \
     -d '{"title":"API project","description":"Created through the REST API"}' \
     http://localhost:8080/projects                                             # 201 + the project
curl "${H[@]}" -H "Content-Type: application/json" -d '{"title":""}' http://localhost:8080/projects   # 422 + errors
curl "${H[@]}" -X PUT -H "Content-Type: application/json" -d '{"title":"Renamed"}' http://localhost:8080/projects/4  # 200
curl "${H[@]}" -X DELETE http://localhost:8080/projects/4                       # 200 {"deleted":true}
curl "${H[@]}" http://localhost:8080/projects/4                                 # 404
curl -H "Accept: application/json" http://localhost:8080/projects               # 401 Access Denied (no key)
```
Caligrafy now sends real HTTP status codes: set yours with `http_response_code()` before returning (201 created,
404 not found, 422 invalid). The original course returned 200 with an error message; clients had to parse the body.
The JSON includes a `table` field: that is how Caligrafy models serialize.

## With Claude
Ask: *"Test every /projects API route with curl using the API_KEY from .env without printing it, and report the
status codes."* or ask the reviewer: *"Use the caligrafy-reviewer agent on the Showcase controller."*

**Check:** the key is read from `.env`, never pasted into chat or committed; statuses match the table above; no route
answers 200 for a missing project.

**Prefer** Manual the first time (you will debug with curl for the rest of your career), Claude for full sweeps.

**Part 1 is done:** compare with `checkpoints/part-1-server` (tag `part-1`).
