# 04 Pug views and forms

**Goal:** the projects list, a project page and a create/edit form in the Caligrafy black and white look.

## Manual
Switch `index()` and `read()` from `api()` to `view()`. The same route still answers JSON when the client sends
`Accept: application/json`, so pages and API share one controller:
```php
return view('Showcase/index', ['projects' => $projects, 'authorized' => authorized()]);
```
`application/views/Showcase/index.pug` extends the default layout (centered title, black and white cards):
```pug
extends ../default/master/layoutblocks

block content
    .spacer-2
    .content-section.equally-spaced-1
        h2.medium-text.horizontal-center.semibold My Projects
        .width-max-800.horizontal-center
            p.horizontal-center A showcase of what I build with Caligrafy
            if $authorized
                a.btn.btn-secondary(href="{$home}projects/new") add a project
        .spacer-4
        .wrapper.bounded-large.content-section
            .row
                each $project in $projects
                    .col-md-6.col-lg-4.mb-4
                        a(href="{$home}projects/{$project->id}")
                            .card.animated.zoomIn.color0.equally-spaced-1.dropshadow
                                h2.medium-text.semibold.textcolor5.reset-margin #{$project->title}
                                p.textcolor5.default-text.block-with-text #{$project->description}
```
Pug variables are PHP variables; `$home` is the base URL. The form (`views/Showcase/form.pug`) needs the CSRF token
and, for PUT/DELETE, the method field:
```pug
form(method='POST', action=$action)
    =csrf()
    if $project && isset($project->id)
        =methodField('PUT')
    .mb-3
        label.default-text.textcolor5(for='title') * title
        input#title.form-control.input-default(type='text', name='title', required, value=($project->title ?? ''))
```
Full views: [index.pug, show.pug, form.pug](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/part-1-server/application/views/Showcase).
Bootstrap 5 replaces Bootstrap 3 from the original course: `form-group` -> `mb-3`, `btn-block` -> `w-100`,
`pull-right` -> `float-end`/`d-flex`, no jQuery (G40).

## With Claude
Ask: *"Create Pug views for Showcase projects (list as cards, project page with edit/delete, one form for create and
edit) extending default/master/layoutblocks, in the existing Caligrafy black and white style, and switch the
controller to view()."*

**Check:** views extend `../default/master/layoutblocks`; every form has `=csrf()`; PUT/DELETE use `=methodField()`;
only Bootstrap 5 classes; the title stays centered; JSON clients still get JSON (`curl -H "Accept: application/json"`).

**Prefer** Manual for the first view (Pug indentation rules), Claude for the repetitive ones, then review the diff.
