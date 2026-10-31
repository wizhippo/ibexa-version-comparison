# Roadmap and open decisions

This document tracks proposed extensions and decisions that need agreement before
implementation. Items have no committed delivery date or priority. See the
[README](../README.md) for current behavior, supported fields, and limits.

## Current scope

The bundle compares two saved versions and selected translations of one content
item. It provides split and unified views, text differences, native field previews,
and permission checks. Comparison is read-only.

## Field type support

Current support includes text, RichText, numbers, booleans, dates/times, URLs,
email, authors, keywords, selections, countries, relations, images, image assets,
files, media, and ISBN fields. User account, Form, Landing Page, and unknown custom
types are currently labelled unsupported; their values are not serialized or rendered.

Proposed extensions:

- **Custom field types:** define an extension mechanism for extracting comparable
  values, deciding equality, and rendering differences safely.
- **Form and Landing Page fields:** investigate their structured values and editor
  integrations before choosing comparison and preview behavior.
- **User account fields:** decide whether any nonsensitive properties should be
  comparable. Preserve the current exclusion until permissions and data exposure
  have been reviewed.

Before supporting a new type, define its meaningful changes, output limits,
permission checks, and fallback behavior. Tests should cover sensitive values,
missing data, unsafe markup, and unsupported historical definitions.

## Historical fidelity

Field definitions currently come from the current content type. Decide whether
historical definitions can be retrieved reliably and how deleted or changed
definitions should be labelled and compared.

Native previews may render the current state of referenced content and external
media. Recursive historical previews would need a separate design for version
selection, permissions, missing references, and cycles.

## RichText comparison

Unified RichText uses the compared version's layout with deleted text inserted at
matching positions. Investigate whether structural changes to tables, lists, or
embedded content need a richer representation. Keep split views available for
reviewing formatting and layout differences.

## Large comparisons

Existing size and diff-work limits protect request processing. Collect representative
large-content cases before changing those limits. Any proposed improvement should
measure runtime and memory use and preserve predictable fallback behavior.

## Workflow boundaries

Restoring versions, merging fields, editing content, and publishing are outside
the current scope. Any proposal to add them requires a separate workflow design
covering permissions, validation, concurrent edits, and explicit confirmation.
