# WordPress Article Sync Plugin

## Overview

Article Sync is a powerful WordPress plugin that automatically collects and republishes content from other WordPress sites. It provides a seamless way to aggregate content from multiple sources while maintaining proper attribution and preserving the original publication details.

## Key Features

### Content Synchronization
- **Multi-Source Support**: Connect to multiple WordPress sites and import their content
- **Selective Importing**: Configure how many posts to import from each source
- **Category Mapping**: Assign specific categories to content from different sources
- **Author Attribution**: Set default authors for imported content
- **Scheduled Synchronization**: Automatically check for and import new content on a customizable schedule

### Content Preservation
- **Original Metadata**: Preserve original publication dates and times
- **Featured Images**: Automatically import and set featured images
- **Source Attribution**: Add automatic source attribution with links to original articles
- **Custom Fields**: Store original article URLs for reference and attribution

### User-Friendly Administration
- **Intuitive Interface**: Manage all your content sources from a clean, user-friendly dashboard
- **Bulk Operations**: Synchronize all sources with a single click
- **Individual Controls**: Synchronize specific sources as needed
- **Detailed Logs**: Track synchronization history with comprehensive logging

### Advanced Features
- **Cron Integration**: Works with WordPress cron and external cron services
- **Error Handling**: Robust error handling with detailed logging
- **Performance Optimization**: Efficient synchronization that minimizes server load
- **Developer Friendly**: Well-documented code with hooks for customization

## Technical Details

- **Requirements**: WordPress 5.0+, PHP 7.4+
- **API Usage**: Utilizes the WordPress REST API for content retrieval
- **Database Impact**: Minimal database overhead with efficient storage of external references
- **Security**: Implements WordPress security best practices with proper data sanitization

## Use Cases

- **Content Aggregation**: Create a central hub for content from multiple related sites
- **News Portals**: Aggregate news from various sources into a single destination
- **Multi-Site Management**: Republish content across a network of related websites
- **Content Syndication**: Easily syndicate content from partner websites

## Getting Started

1. Install and activate the plugin through the WordPress admin interface
2. Navigate to "Article Sync" in your admin menu
3. Add your first source by providing the WordPress site URL
4. Configure category mapping and author settings
5. Click "Synchronize" to import content immediately or wait for the scheduled sync

## Customization

The plugin provides several filters and actions for developers to customize its behavior:
- Modify how content is processed before importing
- Customize the source attribution format
- Extend logging capabilities
- Implement custom error handling

## Support and Contributions

This plugin is actively maintained. For support requests, bug reports, or feature suggestions, please use the GitHub issues section. Contributions via pull requests are welcome.

---

*Article Sync respects content ownership and is designed for use with authorized content sources. Always ensure you have permission to republish content from the sources you connect to.*
