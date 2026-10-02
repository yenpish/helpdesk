# Attendance Management UI notes

These notes record the sources and reasoning behind the current interface so the implementation can be explained accurately during review.

## Learning references

- [Laracasts: Laravel From Scratch (2026 Edition)](https://laracasts.com/series/laravel-from-scratch-2026/) covers Laravel routing and views, shared layout files, extracting reusable Blade components, and a list/detail/filtering/pagination workflow. I used these as learning references for organizing Blade pages and keeping the shared application shell separate from page content.
- [Laravel Blade documentation](https://laravel.com/docs/13.x/blade#layouts-using-components) documents Blade layouts and components. The current application continues to use its existing `@extends` / `@section` layout pattern; this UI pass did not introduce a new component architecture.

The visual design is adapted to this attendance system. The course examples are not presented as the source of the colors or as a screen copied into this project.

## Product rules reflected in the interface

- The product is presented as an Attendance Management System. Helpdesk and ticket terminology is absent from the active navigation and login screen.
- Events are the attendee-facing object. Sessions are managed inside an event. Registrations belong to an event; attendance records belong to a session.
- Dashboard totals stay on the dashboard. The event list is for finding records and taking actions.
- Admin navigation groups account oversight, audit history, and configurable event types and locations. Organizers see event management within their existing ownership permissions.
- Create and edit workflows remain separate pages. Lists link to record details and explicit actions.
- The event PIN remains event-level, matching the existing attendance flow. No session PIN or attendee-account workflow was added.

## Visual design decisions

- The final interface uses a light working area with a dark shell: sidebar `#18202A`, top bar `#202832`, canvas `#F3F4F2`, white content surfaces, secondary surface `#EEF1F3`, table header `#E8ECEF`, border `#D7DCE1`, text `#1F2933`, and muted text `#66717D`.
- Blue `#2563EB` marks primary actions and links. Muted green, amber, and red identify success, draft/warning, and destructive/cancelled states.
- Tables keep their column headings, record values, and actions together. Narrow screens can scroll wide tables horizontally rather than squeezing fields into unreadable columns.
- Account, event, and session forms use the same labels, field treatment, validation placement, and action placement. Public forms are visually contained so fields remain readable on wide screens.

## Explicit UI decisions

- A registration is marked **Missed** only when it is approved, the session has ended, and no matching session attendance exists. Pending registrations show **Awaiting approval**, future approved registrations show **Not assessed yet**, and rejected or cancelled registrations show **Not eligible**. This avoids presenting a future or unapproved attendee as absent.
- Organizer dashboard totals and session lists are scoped to events they organize, following the ownership rule already enforced by the event controllers.
- Auto-generated daily sessions use the event's daily start/end times. If those times would create a zero-length or reversed session, the fallback is one hour, bounded by the event end and calendar day. This is an implementation rule for the already-decided one-session-per-day behavior.
- The existing `user` account role is kept for compatibility and displayed as **User**. That role receives the public events and attendance pages; a separate account workflow is not introduced.
- New system accounts can be assigned the confirmed operational roles (System Admin or Organizer). Existing `user` accounts display as **User**; other historical role values are identified as legacy roles. The retired Technician label is not exposed in the active product UI.
- Audit records retain the actor, action, module, record, description, and time. New account snapshots contain only name, email, and role; the audit display also filters sensitive and internal fields from older records without changing database history.
- The Events list shows registrations and attendance entries separately. A registration is an expression of intent; an attendance entry records presence at one session. A person attending multiple sessions contributes multiple attendance entries.
- Session Details exposes the actual attendance records for that session. Event Details exports its sessions' attendance entries as CSV; participant contact fields are included, while signatures and geolocation are excluded.
- Event Created By / Updated By describe edits to the Event record. Session records keep their own editor metadata, and Audit Log remains the historical source for actions on child records. Event-level Updated By is not changed by registration or session edits.
- Pagination links use restrained secondary controls. Record names remain ordinary links so navigational links and actions stay visually distinct.
- Registration and attendance require a phone number. Registration checks phone duplicates within the same event after removing common punctuation; the same number remains valid for another event. Email continues to be the attendance matching identifier.

These are interface and presentation decisions. They do not claim to be additional requirements from the internship supervisor.
