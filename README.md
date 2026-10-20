# NSBM StudyCircle

Peer-to-Peer Academic Knowledge Sharing & Collaborative Study Platform  
NSBM Green University — Faculty of Computing

---

## Overview

**NSBM StudyCircle** is a collaborative web platform designed for NSBM undergraduates to share lecture notes, participate in course-specific academic forums, organize private study groups with secret join codes, and host live video study sessions using integrated WebRTC technologies.

---

## Core Features

- **Study Notes Repository**: Peer-reviewed lecture notes, past papers, and summaries categorized by faculty, year level, and course module.
- **Client-Side Instant Filter Engine**: Dynamic filtering by faculty, year, category, and batch with real-time text query debouncing.
- **Academic Discussion Forum**: Course-level Q&A discussions with interactive emoji reactions and threaded replies.
- **Private Study Groups & Live Video**: Study group collaboration with 6-character invite codes and embedded Jitsi Meet video rooms.
- **Administrative Portal**: Complete management suite for reviewing note submissions, monitoring forum activity, and auditing users.
- **Mobile-First Responsive Design**: Tailored mobile experience featuring fixed bottom navigation, swipeable category pills, and off-canvas navigation.

---

## Technology Stack

- **Backend**: PHP 8.x (Object-Oriented & Procedural MVC Separation)
- **Database**: MySQL with PDO prepared statements
- **Frontend**: Vanilla JavaScript (ES6+), Modern Semantic HTML5
- **Styling**: Responsive Custom CSS3 (Flexbox & CSS Grid)
- **Video Conferencing**: Jitsi Meet External API (WebRTC)
- **Server**: Apache (XAMPP Environment)

---

## Project Structure

```
nsbm-studycircle/
├── admin/               # Administrative moderation & management dashboard
│   ├── index.php        # System overview and analytics metrics
│   ├── notes.php        # Note review and approval queue
│   ├── students.php     # Student accounts management
│   ├── forum.php        # Discussion moderation
│   └── approve.php      # Note approval controller
├── assets/
│   ├── css/             # Modular application stylesheets
│   ├── js/              # Modular JavaScript components
│   └── img/             # Visual branding & university crest assets
├── includes/            # Core backend utilities
│   ├── db.php           # PDO database connection
│   ├── functions.php    # Route guards & helper functions
│   └── footer.php       # Global footer & mobile navigation
├── student/             # Student portal controllers
│   ├── index.php        # Notes explore dashboard
│   ├── materials.php    # Note submission & personal uploads
│   ├── download.php     # Secure note streaming
│   ├── forum.php        # Discussion forum feed
│   ├── forum_view.php   # Threaded forum discussion
│   ├── groups.php       # Study groups directory
│   └── group_view.php   # Study group workspace & video room
├── database.sql         # Relational database schema (11 tables)
├── index.php            # Landing page & user authentication
├── logout.php           # Session termination
└── register.php         # Student registration
```

---

## Project Team & Responsibilities

| Member | Role | Primary Modules |
| :--- | :--- | :--- |
| **Achira Chamika** (Tech Lead) | Software Architect & Tech Lead | System architecture, routing guards, global shell & integration |
| **Isindu Prabath** | Database & Authentication Engineer | Database schema design, user authentication & password hashing |
| **Chethiya Malinda** | Materials Management Specialist | File uploads, validation & secure streaming download controllers |
| **Vihaga Nimsara** | Search & Discovery Engineer | Notes explore dashboard, real-time client-side filter engine |
| **Kaushalya Lakmini** | Collaborative Learning Engineer | Study groups directory, secret join codes & Jitsi Meet video integration |
| **Divya Oshadi** | Community & Forum Specialist | Academic discussion forum, interactive reactions & threaded replies |
| **Chanithi Dilansa** | Administration & Moderation Engineer | Admin portal, metric counters & content moderation queue |
| **Telakshi Ransala** | UI/UX & Mobile Responsiveness Lead | Design system, mobile bottom navigation & cross-device layouts |

---

## Installation & Setup

1. Clone repository to your local web root:
   ```bash
   git clone https://github.com/acsmew/NSBM-Study-Circle.git
   ```
2. Import `database.sql` into MySQL using phpMyAdmin or MySQL CLI:
   ```bash
   mysql -u root -p nsbm_studycircle < database.sql
   ```
3. Configure database credentials in `includes/db.php` if required.
4. Launch Apache & MySQL in XAMPP and open `http://localhost/nsbm-studycircle/` in your browser.
