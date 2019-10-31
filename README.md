# Skyline Component SCSS
The SCSS is able to deliver directly SCSS files to the client.  
It automatically compiles, delivers and caches sources.

#### Installation
```bin
$ composer require skyline/component-scss
```

Now you can define SCSS components in any component.cfg.php (vendors) or components.config.php (main application) files.

````php
<?php
use Skyline\Component\SCSS\SCSSComponent;

return [
    'MySCSSComponent' => [
        'icon' => NULL, // ....
        'css' => new SCSSComponent(
 /* link */ '/Public/what-ever/you/want/as/link.css',
 /* media */'all',
            [ // Further options
                SCSSComponent::OPTION_INPUT_FILE => 'SkylineAppData/Style/main.scss',
                SCSSComponent::OPTION_LIBRARY_MAPPING => [
                    'bootstrap' => __DIR__ . "/../../vendor/twbs/bootstrap/scss",
                    __DIR__ . "/../../vendor/twbs/bootstrap/scss"
                ]
            ]
        )
    ]
];
````

Now any layout or template is able to @require MySCSSComponent and use its compiled contents.

Note that SCSSComponent::OPTION_LIBRARY_MAPPING is an array defining keys as strings for library access or integers for directly path importing.

```scss
@import "variables";  // is allowed and will search in the same directory as the scss file is for a file named variables.scss or variables.css

@import "vendor/twbs/bootstrap/scss/variables"; // Will follow the link and import
@import "bootstrap:variables"; // Is the same when SCSSComponent::OPTION_LIBRARY_MAPPING['bootstrap'] declares a directory.

```

