workspace {

    model {
        # @see https://www.drupal.org/node/3471372
        sb = person "Ambitious Site Builder" {
            tags "SB-primary"
        }
        # @see "Content Editor": https://www.drupal.org/node/3471465
        cc = person "Content Creator" {
            tags "CC-primary"
        }
        # @see "Digital Strategist": https://www.drupal.org/node/3471468
        cm = person "Content Manager" {
            tags "CM-primary"
        }
        # @see https://www.drupal.org/node/3471462
        fe = person "Front-End Developer" {
            tags "DEV-primary"
        }
        # @see https://www.drupal.org/node/3471461
        be = person "Back-End Developer" {
            tags "DEV-primary"
        }
        drupal = softwareSystem "Drupal + Canvas" {
            !adrs adr
            sb -> this "Defines site structure"
            cc -> this "Creates content within structure"
            cm -> this "Manages content within structure"
            be -> this "Develops modules, block plugins, field formatters"
            fe -> this "Develops themes, design systems, SDCs"
            canvas-admin-ui = container "Canvas admin UI" {
                description "Define design system and how it is available for Content Creators by opting in SDCs, defining field types for SDC props, defining default layout, defining Content Creator’s freedom…"
                tags "SB-primary"
                sb -> this "Defines data model + Canvas design system"
            }
            canvas-specific-config = container "Canvas-specific Config" {
                description "Validatable to the bottom, to guarantee no content breaks while codebase & config evolve"
                tags "SB-primary"
                url "https://www.drupal.org/project/canvas/issues/3444424"
                canvas-admin-ui -> this "Creates and manages"
                canvas-config-component = component "Canvas Component" {
                  description "Declares how to make a type=Element or type=Component available within Canvas."
                  technology "Config entity"
                  tags "SB-primary"
                  sb -> this "Opts in + configures default SDC prop values"
                }
                canvas-config-entityviewdisplay = component "Canvas Entity View Display" {
                  description "Defines the default layout (component tree)."
                  technology "Config entity third party settings"
                  tags "SB-primary"
                  canvas-config-component -> this "Is placed in"
                  sb -> this "Creates default layout
                }
            }

            #
            # The 4 current component types.
            #
            # @see https://www.drupal.org/project/canvas/issues/3454519
            #
            canvas-code-component-element = container "Canvas 'Element' Component Type" {
                description "N visible in UI — exposes 1 SDC 'directly', in principle only 'simple' SDCs, BUT FOR EARLY MILESTONES THIS COULD BE ANY SDC!"
                url "https://www.drupal.org/project/canvas/issues/3444417"
                tags "proxy"
                canvas-config-entityviewdisplay -> this "Places 1 or more"
                canvas-config-component -> this "Configures available instances"
            }
            canvas-code-component-component = container "Canvas 'Component' Component Type" {
                description "N visible in UI — a composition of SDCs built in Canvas's 'Theme Builder', NOT FOR EARLY MILESTONES!"
                url "https://www.drupal.org/project/canvas/issues/3444417"
                tags "proxy"
                canvas-config-entityviewdisplay -> this "Places 1 or more"
                canvas-config-component -> this "Configures available instances"
            }
            canvas-code-component-block = container "Canvas 'Block' Component Type" {
                description "Only 1 visible in UI — allows 1) selecting any block plugin, 2) configuring its settings"
                url "https://www.drupal.org/project/canvas/issues/3444417"
                tags "proxy"
                canvas-config-entityviewdisplay -> this "Places 1 or more"
            }
            canvas-code-component-field-formatter = container "Canvas 'Field Formatter' Component Type" {
                description "Only 1 visible in UI — allows 1) selecting any field on host entity type, 2) selecting any formatter, 3) configuring its settings"
                url "https://www.drupal.org/project/canvas/issues/3444417"
                tags "proxy"
                canvas-config-entityviewdisplay -> this "Places 1 or more"
            }

            drupal-code-sdc = container "Single Directory Components" {
                description "SDCs in both modules and themes, both contrib & custom — aka 'Code-Defined Components'"
                tags "DEV-primary"
                canvas-code-component-element -> this "Uses"
                canvas-code-component-component -> this "Uses"
                fe -> this "Creates"
            }
            drupal-code-block = container "Block (block plugin)" {
                description "Installed block plugins"
                tags "DEV-primary"
                canvas-code-component-block -> this "Proxies"
                be -> this "Creates"
            }
            drupal-code-field-formatter = container "Field formatter" {
                description "Installed field formatter plugins"
                tags "DEV-primary"
                canvas-code-component-field-formatter -> this "Proxies"
                be -> this "Creates"
            }
            canvas-ui = container "Canvas UI" {
                description "The dazzling new UX! Enforces guardrails of data model + design system"
                url "https://www.drupal.org/project/canvas/issues/3454094"
                tags "CC-primary,CM-primary"
                cc -> this "Creates content within guardrails: places Canvas Components in open slots, defines SDC prop values for Canvas components in default layout and Canvas components in open slots, maybe overrides default layout"
                cm -> this "Uses this UI to review changes to Canvas Content created by Content Creators prior to publishing"
                canvas-specific-config -> this "Steers"
                canvas-code-component-element -> this "Is available in left sidebar (assuming open slots and/or unlocked component subtrees) of"
                canvas-code-component-component -> this "Is available in left sidebar (assuming open slots and/or unlocked component subtrees) of"
                canvas-code-component-block -> this "Is available in left sidebar (assuming open slots and/or unlocked component subtrees) of"
                canvas-code-component-field-formatter -> this "Is available in left sidebar (assuming open slots and/or unlocked component subtrees) of"
                canvas-config-entityviewdisplay -> this "Defines the default layout (or empty if none)"
            }
            drupal-config = container "Config" {
                description "All Drupal config — including data model."
                technology "Drupal configuration system"
                tags "SB-primary"
                canvas-specific-config -> this "Are additional config entities + third-party settings on existing config"
            }
            drupal-code = container "Code" {
                description "Drupal core + installed modules + installed themes"
                tags "DEV-primary"
                # fe -> this "Creates SDCs and themes (CSS & JS)"
                # be -> this "Creates modules (PHP)"
                this -> drupal-code-sdc "Contains"
                this -> drupal-code-block "Contains"
                this -> drupal-code-field-formatter "Contains"
            }
            drupal-site = container "Drupal site" {
                description "Drupal as we know it"
                canvas-ui -> this "Overrides the add/edit UX for content entities configured to use Canvas"
                this -> drupal-config "Uses"
                this -> drupal-code "Powered by"
            }
            container "Database" {
                description "Content entities etc."
                drupal-site -> this "Reads from and writes to"
                canvas-ui     -> this "Reads from and writes to"
            }
        }
    }

    views {
        systemContext drupal {
            include *
            autolayout lr
        }

        container drupal {
            include *
            autolayout lr
        }

        component canvas-specific-config {
            include * drupal
            autolayout tb
            description "The config entities that power Canvas. Designed for layering on top of code (such as SDCs) and allowing versioning/sharing using the existing configuration system."
        }

        styles {
            element "SB-primary" {
                background orange
                color #ffffff
            }
            element "CC-primary" {
                background green
                color #ffffff
            }
            element "DEV-primary" {
                background red
                color #ffffff
            }
            element "proxy" {
                background gray
                color #ffffff
            }
        }


        theme default
    }

}
