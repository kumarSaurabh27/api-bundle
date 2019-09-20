<p align="center"><a href="https://www.uvdesk.com/en/" target="_blank">
    <img src="https://s3-ap-southeast-1.amazonaws.com/cdn.uvdesk.com/uvdesk/bundles/webkuldefault/images/uvdesk-wide.svg">
</a></p>

[UVDesk Community Edition][1] is an easy-to-use, highly customizable open-source **helpdesk solution** built on top of the reliable [Symfony][2] **PHP framework**, enabling organizations to provide their customers with the best level of support solution possible.

APIBundle
--------------

The **APIBundle** correlates with the Representational State Transfer category (REST) that allows to perform several actions like reading, editing, deleting, adding data of the helpdesk system. The resources like tickets, agents, customers can be controlled using API. It also supports CORS ie. Cross Origin Resource Sharing.

The API bundle comes loaded with the following features:

  * **TicketSystem** - Easily get ticket list and create ticket,delete ticket.
  
Installation
--------------
This bundle can be easily integrated into any Symfony application (though it is recommended that you're using [Symfony 4][3], or your project has a dependency on [Symfony Flex][4], as things have changed drastically with the newer Symfony versions). Before continuing, make sure that you're using PHP 7 or higher and have [Composer][5] installed. 

To require the core framework bundle into your symfony project, simply run the following from your project root:

```bash
$ composer require uvdesk/api-bundle
```

License
--------------

The **APIBundle** and libraries included within the bundle are released under the MIT or BSD license.

[1]: https://www.uvdesk.com/
[2]: https://symfony.com/
[3]: https://symfony.com/4
[4]: https://flex.symfony.com/
[5]: https://getcomposer.org/
