# Driesnote Vienna 2025 try out yourself demo

## Requirements
* **IMPORTANT:** An OpenAI account on [tier 4](https://platform.openai.com/docs/guides/rate-limits/usage-tiers) at least.
* DDEV 1.24+

## Description

A demo Drupal 11 for you to try out what Dries showed on stage. This will install:
* Drupal CMS+Mercury+Canvas
* Canvas AI with OpenAI provider configured
* Milvus configured
* Medias to pick from (from Pexels and AI Generated)
* Patches needed for Canvas AI to work well - these have issues in the issue queue to be committed.

## Notes

This is to try it out, this goes without saying but don't use this for production or any other purposes than trying it out.

## To install
* On Mac, make sure to run in bash and not zsh and use the latest version of OrbStack.
* `cp .ddev/.env.template .ddev/.env` and set your OpenAI key in the file.
* From the root of the project `ddev demo-setup`

**Note** Everytime you run `ddev demo-setup` it will delete everything and start from scratch.

