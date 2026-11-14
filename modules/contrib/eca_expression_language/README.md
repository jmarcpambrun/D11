# ECA Expression Language

Easily add complex composite conditions or calculated values as token in your
ECA workflows using and, or, (), calculation... The module provides symfony
ExpressionLanguage component integration to ECA.

## Features

-Expression Language Condition : making simple or complex workflows much easier
to build
-(Upcoming) Expression Language Value : add calculated values to your workflows
as easily as Excel formulas

Condition expression example :
```
[entity:nid] in [ 2, 6, 7 ]
and ("[entity:type]" == "article" or [user:uid] == 1)
```

## Post-Installation

-Enable the module.
-Create an ECA workflow
-Click on a link and select "Expression Language Condition"
-Enter an expression using tokens and ExpressionLanguage Syntax
