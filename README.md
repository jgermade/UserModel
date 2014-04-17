UserModel(.php)
============

>	UserModel - Provides an automated method to access user data.

>	Copyright (C) 2014  Jesús Manuel Germade Castiñeiras

>	This program is free software: you can redistribute it and/or modify
>	it under the terms of the GNU General Public License as published by
>	the Free Software Foundation, either version 3 of the License.

=======


##### How to use

    require("UserModel.php");
    $user = new UserModel();
    
    if( $user->isLogged() ) {
    
      echoJSON([ "items" => $user->model("items")->get() ]);
      
    } else {
    
      http_response_code(403);
      echoJSON([ "error" => "unauthorized" ]);
      
    }