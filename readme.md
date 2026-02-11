# Local Parce - Q&A Chat Widget

A chat that allows users to get answers in context, depending on their location and mediated by AI.

## Features

- **Floating Chat Bubble**: Unobtrusive chat widget that floats in the bottom-right corner of the page
- **Responsive Design**: Works seamlessly on desktop and mobile devices
- **Accessibility**: Built with ARIA labels and keyboard navigation support

### Future Implementation

The current UI is fully functional. The backend implementation will include:

1. **Message Storage**: Database tables for storing chat history
2. **Admin Dashboard**: UI for managing questions and responses
3. **Analytics**: Tracking and reporting on chat usage

## Installation Guide

### Requirements

- Moodle 5.1 or later

### Installation Steps

#### 1. File Installation

Copy the entire `local/parce` directory to your Moodle installation:

```bash
cp -r parce /path/to/moodle/local/
```

Ensure the directory structure matches:
```
/path/to/moodle/local/parce/
├── amd/
├── classes/
├── db/
├── lang/
├── templates/
├── [...others...]
├── lib.php
├── settings.php
├── version.php
└── readme.md
```

#### 2. Plugin Registration

The plugin will be automatically detected by Moodle. You may need to visit:

**Site Administration → Notifications**

Moodle will detect the new plugin and prompt you to install it.

Alternatively, use the command line:

```bash
php admin/cli/upgrade.php
```

#### 3. Enable the Plugin

After installation, navigate to:

**Site Administration → Plugins → Local Plugins → Parce Chat Widget**

The plugin should be listed and enabled by default.

#### 4. Configuration (Optional)

Go to **Site Administration → Plugins → Local Plugins → Manage Plugins** and find "Parce Chat Widget".

Configure the following settings:

- **Enable Parce Chat Widget**: Toggle to enable/disable globally
- **Chat Window Title**: Customize the title (default: "Questions & Answers")
- **Enable for Guest Users**: Allow guests to use the chat (default: off)

### Verification

#### Visual Verification

1. Log in to your Moodle instance as a teacher or administrator
2. Navigate to any page (Dashboard, Course page, etc.)
3. Look for a blue floating circle with a "?" in the bottom-right corner
4. Click the bubble to open the chat window

#### Console Verification

Open your browser's Developer Console (F12 or Ctrl+Shift+I) and check for any errors:

```javascript
// In the console, you should see the chat module loaded
require(['local_parce/chat'], function(chat) {
    console.log('Chat module loaded successfully');
});
```

## Browser Support

- Chrome/Chromium (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Accessibility

The widget includes:
- ARIA labels for screen readers
- Keyboard navigation support
- Focus management
- High contrast mode support
- Reduced motion support

## License

GNU GPL v3 or later

## Author

David Herney @ BambuCo (2026)
