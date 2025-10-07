# AI Context

## INTRODUCTION

The AI Context module provides site-specific AI context entities and dynamic routing for sub-agents. This module allows you to create, manage, and organize contextual information that can be intelligently selected and provided to AI agents based on the current task or user prompt. It enables AI systems to access relevant site-specific knowledge and information to improve their responses and decision-making.

## REQUIREMENTS

This module relies on the following dependencies:
- AI (Artificial Intelligence) module - Core AI functionality
- AI Agents module - Agent management system
- Taxonomy module - For organizing contexts with tags

## INSTALLATION

Install as you would normally install a contributed Drupal module.

## FEATURES

- **Context Management**: Create and manage AI context entities with markdown content
- **Tag-based Organization**: Organize contexts using taxonomy tags for better categorization
- **Intelligent Selection**: Automatically select relevant contexts based on task descriptions
- **Agent Pools**: Configure specific context pools for different AI agents
- **Function Calling**: Provides AI function calls for retrieving relevant context
- **Configurable Strategy**: Choose from different context selection strategies (keyword-based or LLM-assisted)
- **Administrative Interface**: Full CRUD operations through Drupal's admin interface

## CONFIGURATION

After installation, configure the module at:
- Main settings: `/admin/config/ai/ai-context/settings`
- Manage contexts: `/admin/config/ai/contexts`
- Manage agent pools: `/admin/config/ai/ai-context/pools`
- Default settings include:
  - Strategy: keyword
  - Max contexts: 3
  - Max tokens: 1200

## PERMISSIONS

The module provides the following permission:
- **Administer AI Context**: Manage AI Context entities, settings, and pools (restricted access)
