<?php

    # -- INICIALIAZAÇÃO --------------------------------------------------------

    require_once "bootstrap.php";

    # -- USED NATIVE CLASSES ---------------------------------------------------

    use backend\User;
    use backend\Form;
    use backend\DashboardComposer;
    use backend\Frontend;
    use backend\backendIndex;

    # -- USED VENDORS CLASSES --------------------------------------------------

    use vendor\Symfony\Component\Yaml\Yaml;
    
    
    # -- EXECUTA SOMENTE SE EXISTIR USUÁRIO LOGADO -----------------------------
    
    if( isset($_SESSION['user']) && !empty($_SESSION['user']) ){
        
    
        # -- VARIAVEIS ---------------------------------------------------------

        $menuModule = "";
        $menuParts = "";
        $menu = Yaml::parse( file_get_contents("menu.yml") );

        # -- DADOS DO USUÁRIO LOGADO -------------------------------------------

        $_M_USER['id']          = ( isset($_SESSION['user']['id']          ) ) ? $_SESSION['user']['id']          : '' ;
        $_M_USER['login']       = ( isset($_SESSION['user']['login']       ) ) ? $_SESSION['user']['login']       : '' ;
        $_M_USER['first_name']  = ( isset($_SESSION['user']['first_name']  ) ) ? $_SESSION['user']['first_name']  : '' ;
        $_M_USER['middle_name'] = ( isset($_SESSION['user']['middle_name'] ) ) ? $_SESSION['user']['middle_name'] : '' ;
        $_M_USER['last_name']   = ( isset($_SESSION['user']['last_name']   ) ) ? $_SESSION['user']['last_name']   : '' ;
        $_M_USER['name']        = preg_replace("/\ {1,4}/i", " ", "{$_M_USER['first_name']} {$_M_USER['middle_name']} {$_M_USER['last_name']}");

        // -- EXECUTA ADD ON BOOTSTRAP -----------------------------------------

        foreach(json_decode("{{$_M_THIS_CONFIG['bootstrap']}}") as $key=>$path){
           require_once $path;
           new $key($_M_THIS_CONFIG, $_M_USER, $menu);
        }
        
        # -- MONTA O MENU ------------------------------------------------------

        function createMenuRecursive($array, $deep=0, $deepClass=1){
            $indent = 4;
            $pad0 = str_pad("", $deep);
            $pad1 = str_pad("", $deep+($indent) );
            $pad2 = str_pad("", $deep+($indent*2) );

            $ret = $pad0 . "<ul class='deep_$deepClass'>\r\n";
            foreach ($array as $key => $val) {
                $ret .= $pad1 . "<li>\r\n";
                
                if(isset($val['link'])){
                    //
                    parse_str( preg_replace("/^\?/", "", $val['link']), $mLnk);
                    
                    //                    
                    if( !in_array($mLnk['path'], $_SESSION['user']['permissions']['backend']['hide-menu-by-path']) ){
                        // os arrays de comparação devem 
                        // ter no minimo estas chaves
                        $arrCompare['path'] = "";
                        $arrCompare['module'] = "";
                        $arrCompare['action'] = "";
                        $arrCompare['module'] = "";

                        // array do menu atual
                        $mLnk = array_replace($arrCompare, $mLnk);
                        $mLnk['module'] = preg_replace("/\/.*$/", "", $mLnk['path']);

                        // array da pagina/url atual (paginaa que esta sendo mostrada no browser)
                        $pLnk = array_replace($arrCompare, $_GET);
                        $pLnk['module'] = preg_replace("/\/.*$/", "", $pLnk['path']);

                        // comparações para adicionar classes
                        if($pLnk['module'] == $mLnk['module']){
                            $cssClassByModule = "active-by-module";
                            $cssClassByForm   = ( $pLnk['path']   == $mLnk['path']   ) ? "active-by-form"   : "";
                            $cssClassByAction = ( $pLnk['action'] == $mLnk['action'] ) ? "active-by-action" : "";
                            $cssClass         = ( $pLnk == $mLnk ) ? "active" : "" ;
                        }else{
                            $cssClassByModule = "";
                            $cssClassByForm   = "";
                            $cssClassByAction = "";
                            $cssClass         = "";
                        }
                        $cssClass = trim(preg_replace("/[ ]+/", " ", "$cssClassByModule $cssClassByAction $cssClassByForm $cssClass"));

                        // 
                        $ret .= $pad2 . "<a class='$cssClass' href='{$val['link']}'>{$val['label']}</a>\r\n";
                    }
                  
                }else{
                    // Recursividade de modulo
                    if( isset($val['load-from-module']) && is_string($val['load-from-module']) ){
                        // verifica se o usuário pode ver este módulo
                        if( 
                            @in_array('all', $_SESSION['user']['permissions']['backend']['show-module'] ) ||
                            @in_array($val['load-from-module'], $_SESSION['user']['permissions']['backend']['show-module'] ) 
                        ){
                            $ret .= $pad2 . "<span>{$val['label']}</span>\r\n";
                            
                            // variaveis comuns
                            global $menuParts;
                            global $menuModule;

                            // verifica se o menu do modulo é ativo
                            $cssClass = (isset($_GET['path']))? $_GET['path'] : "";
                            $cssClass = ( preg_replace("/\/.*$/", "", $cssClass) == $val['load-from-module'] )? "active" : "deactive";

                            // cria item do menu de modulo
                            $menuModule .= "\r\n    <li class='{$val['load-from-module']}'><a class='$cssClass' href='{$val['module-start-url']}'>{$val['label']}</a></li>";

                            // cria o itens filhos a partir do menu de formulário no main-menu
                            $smenu = Yaml::parse( file_get_contents("modules/{$val['load-from-module']}/menu.yml") );
                            $menuParts[ $val['load-from-module'] ] =  createMenuRecursive($smenu, $deep+($indent*2), $deepClass+1);

                            // retorna o menu
                            $ret .= $menuParts[ $val['load-from-module'] ];
                        }
                    }

                    // Recursividade de menu
                    elseif( isset($val['submenu']) && is_array($val['submenu']) ){
                        $ret .= $pad2 . "<span>{$val['label']}</span>\r\n";
                        $ret .= createMenuRecursive($val['submenu'], $deep+($indent*2), $deepClass+1);    
                    }
                }


                $ret .= $pad1 . "</li>\r\n";
            }
            $ret .= $pad0 . "</ul>\r\n";
            return $ret;
        }

        $_M_MENU = createMenuRecursive($menu);
        $_M_MENU_MODULE = "<ul>$menuModule\r\n</ul>";
        $_M_MENU_PARTS = $menuParts;

        unset($menuModule);
        unset($menuParts);
        unset($menu);
        
    }
    
    // -- DECIDE QUAL AÇÃO EXECUTAR --------------------------------------------

    $action = (isset($_GET['action']))? $_GET['action'] : "";
    switch( $action ) {
        // MOSTRA A INTERFACE GRÁFICA DO
        // DASHBOARD ESPECIFÍCO
        case "view-dashboard":{
            if( !User::check("backend/forms/view/dashboard", "bool") )
                User::check("backend/modules/view/dashboard/{$_GET['path']}", "html");
            $dashboard = new DashboardComposer();
            $dashboard->viewDashboard( $_GET['path'], "dashboard.html" );
            break;
        }
        
        // MOSTRA A INTERFACE GRÁFICA DA
        // TELA DE FORMULÁRIO DE INSERÇÃO
        case "view-insert-form":{
            if( !User::check("backend/forms/view/insert", "bool") )
                User::check("backend/modules/view/insert/{$_GET['path']}", "html");
            backendIndex::viewFormInsert("form.html");
            break;
        }

        // MOSTRA A INTERFACE GRÁFICA DA
        // TELA DE FORMULÁRIO DE ATUAIZAÇÃO
        case "view-update-form":{
            if( !User::check("backend/forms/view/update", "bool") )
                User::check("backend/modules/view/update/{$_GET['path']}", "html");
            backendIndex::viewFormUpdate("form.html");
            break;
        }

        // BUSCA LISTA DE DADOS REFERENTE 
        // AO FORMULÁRIO NO BANCO DE DADOS
        case "view-list-form":{
            if( !User::check("backend/forms/view/list", "bool") )
                User::check("backend/modules/view/list/{$_GET['path']}", "html");
            backendIndex::viewFormList("list.html");
            break;
        }

        // BUSCA LISTA DE DADOS REFERENTE 
        // AO FORMULÁRIO NO BANCO DE DADOS
        // E O FORMULÁRIO DE INSERÇÃO
        case "view-inTabs-form":{
            if( !User::check("backend/forms/view/inTabs", "bool") )
                User::check("backend/modules/view/inTabs/{$_GET['path']}", "html");
            backendIndex::viewFormInTabs("listAndInsert.html");
            break;
        }

        // MOSTRA A INTERFACE GRÁFICA DA
        // TELA DE FORMULÁRIO DE INSERÇÃO
        // EM JANELA
        case "view-insert-window-form":{
            if( !User::check("backend/forms/view/insert", "bool") )
                User::check("backend/modules/save/insert/{$_GET['path']}", "html");
            backendIndex::viewFormInsert("form-window.html");
            break;
        }

        // MOSTRA A INTERFACE GRÁFICA DA
        // TELA DE FORMULÁRIO DE ATUAIZAÇÃO
        // EM JANELA
        case "view-update-window-form":{
            if( !User::check("backend/forms/view/update", "bool") )
                User::check("backend/modules/save/update/{$_GET['path']}", "html");
            backendIndex::viewFormUpdate("form-window.html");
            break;
        }

        // BUSCA LISTA DE DADOS REFERENTE 
        // AO FORMULÁRIO NO BANCO DE DADOS
        // EM JANELA
        case "view-list-window-form":{
            if( !User::check("backend/forms/view/list", "bool") )
                User::check("backend/modules/save/list/{$_GET['path']}", "html");
            backendIndex::viewFormList("list-window.html");
            break;
        }

        // MOSTRA A INTERFACE GRÁFICA DA
        // TELA DE SOLICITAÇÃO DE RECUPERAÇÃO DE SENHA
        case "view-change-password":{
            backendIndex::viewrecoverPassword($_GET['hash']);
            break;
        }

        // DELETA UM FORMULÁRIO
        case "delete-form":{
            if( !User::check("backend/forms/save/delete", "bool") )
                User::check("backend/modules/save/delete/{$_GET['path']}", "json");
            $form = new Form();
            echo json_encode( $form->deleteForm( $_GET['path'], $_GET['id']) );
            break;
        }

        // INSERE OU ATUALIZA DADOS 
        // NO BANCO DE DADOS
        case "save-form":{
            if( preg_match("/update\:.*/i", $_POST['_M_ACTION']) ){
                if( !User::check("backend/forms/save/update", "bool") )
                    User::check("backend/modules/save/update/{$_GET['path']}", "json");
            }else{
                if( !User::check("backend/forms/save/insert", "bool") )
                    User::check("backend/modules/save/insert/{$_GET['path']}", "json");
            }
               
            $form = new Form();
            echo json_encode( $form->saveForm( $_GET['path'], $_POST) );
            break;
        }

        // EXECUTA AJAX DE ALGUM TYPE ESPECIFICO
        case "type-ajax":{
            $type = $_GET['type'];
            if( file_exists($fileType = "types/$type/$type.php") ){
                require_once $fileType;
                $obj = new $type();
                if( method_exists ( $obj , "ajax" ) ){
                    $obj->ajax();
                }else{
                    echo "Metodo ajax não foi encontrado em '$fileType'.";
                }
            }else{
                echo "Erro ao carregar ajax do type '$fileType'.";
            }
            break;
        }

        // EFETUA LOGIN DE USUÁRIO
        case "login":{
            echo json_encode( User::login( $_POST['login'], $_POST['password']) );
            break;
        }

        // EFETUA LOGOUT DE USUÁRIO
        case "logout":{
            User::logout();
            header("Location: . ");
            break;
        }

        // SOLICITA RECUPERAÇÃO DE SENHAS
        case "recover-password":{
            echo json_encode( User::recoverPassword($_POST['login']) );
            break;
        }

        // ALTERA A SENHA COM BASE
        // NA SOLICITAÇÃO DE RECUPERAÇÃO DE SENHA
        case "change-password":{
            echo json_encode( User::recoverPasswordChangePassword($_POST['recovery_hash'], $_POST['password'], $_POST['password-1']) );
            break;
        }

        // AÇÃO PADRÃO
        default:{
            header("Location: {$_M_THIS_CONFIG['start-place']}");
            break;
        } 
    }