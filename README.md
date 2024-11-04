# WordPress Article Sync Plugin

A WordPress plugin that allows you to synchronize articles from multiple external WordPress websites to your own WordPress installation.

## Description

This plugin enables you to automatically import articles from other WordPress websites while maintaining proper attribution through backlinks to the original content. Each source can be configured individually with its own author, category, and number of posts to sync.

## Features

- **Multiple Sources**: Add and manage multiple WordPress websites as content sources
- **Custom Settings per Source**:
  - Assign specific authors for imported content
  - Set target categories for imported posts
  - Configure number of posts to sync (1-100 posts)
- **Media Handling**:
  - Automatic import of featured images
  - Creation of proper WordPress media attachments
- **Content Attribution**:
  - Automatic backlinks to original articles
  - Preservation of original publication dates
- **User-Friendly Interface**:
  - Easy-to-use admin interface
  - Individual sync buttons per source
  - Simple source management

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Source websites must have WordPress REST API enabled

## Installation

1. Upload the plugin files to the `/wp-content/plugins/article-sync` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Article Sync' in the admin menu to configure your sources

## Configuration

### Adding a New Source

1. Navigate to the 'Article Sync' menu in your WordPress admin panel
2. Click 'Add New Source'
3. Configure the following settings:
   - WordPress URL: The URL of the source website
   - Author: Select who should be set as the author for imported posts
   - Category: Choose the category for imported posts
   - Posts per Sync: Set how many posts should be imported per sync (1-100)
4. Click 'Save All Sources'

### Synchronizing Content

1. Go to the 'Article Sync' menu
2. Find the source you want to sync
3. Click the 'Sync Now' button
4. Wait for the synchronization to complete

## Technical Notes

- Uses WordPress REST API for fetching content
- Implements proper error handling and duplicate checking
- Maintains original post dates and times
- Creates proper media attachments for featured images
- Supports SSL connections

## Security

- Implements WordPress nonces for AJAX requests
- Requires proper admin capabilities for management
- Sanitizes all input and output
- Validates external URLs and content

## Support

For bug reports and feature requests, please use the GitHub issue tracker.

## License

This plugin is licensed under the GPL v2 or later. 
