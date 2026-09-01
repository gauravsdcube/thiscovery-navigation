# Thiscovery Navigation

**Version 1.0.0**

Copyright (c) 2026 D Cube Consulting. All rights reserved.

Manage the HumHub site top bar as a three-level menu: Dashboard, Pages, Forms, Maps, groups, and custom URLs. Page Builder remains the page catalog; this module owns the menu itself.

## Features

- **Menu tree** — drag-and-drop, nest up to three levels, unused destinations stay in a tray
- **Sources** — core links, Thiscovery Pages, Forms, Maps, and custom URLs
- **Visibility** — everyone, guests, logged-in users, administrators, or hidden
- **Desktop** — nested dropdowns in the top bar
- **Mobile** — each top-level item can go in the hamburger panel, the floating bottom bar, or be hidden; nested items follow their parent
- **Theme** — hamburger vs floating-bar style, colours, account links, and Legals stay in Thiscovery Theme
- **Admin studio** — collapsible sections, Expand all / Collapse all, and ? guidance

## Requirements

- HumHub **1.18** or later
- Optional: **Thiscovery Theme** (mobile look and feel)
- Optional: **Thiscovery Page Builder**, **Thiscovery Forms**, **Thiscovery Mapping** (catalog sources)

## Installation

1. Copy this module to `protected/modules/thiscovery-navigation`.
2. Enable it: **Administration → Modules → Thiscovery Navigation**.
3. Open **Administration → Thiscovery Navigation** (or `/thiscovery-navigation/admin/index`).
4. Arrange the tree and click **Save order**. On mobile, set hamburger / floating / hide per top-level item.

When the module is enabled, Page Builder, Forms, and Mapping no longer add their own top-bar links. Add those destinations from the unused tray instead.

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).
