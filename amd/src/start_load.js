define([], function() {
    return {
        init: function() {},
        
        moremenu: function(name, href) {
            var link = `<li data-key="spotaward" class="nav-item">
                            <a class="nav-link" href="${href}">${name}</a>
                        </li>`;
            
            var $menu = $("#header .primary-navigation .custom-menu > ul");
            if ($menu.length) {
                $menu.prepend(link);
                return;
            }
            $menu = $("#header .primary-navigation .moremenu > ul");
            if ($menu.length) {
                $menu.prepend(link);
                return;
            }
            
            $menu = $(".navbar .primary-navigation .custom-menu > ul");
            if ($menu.length) {
                $menu.append(link);
                return;
            }
            $menu = $(".navbar .primary-navigation .moremenu > ul");
            if ($menu.length) {
                $menu.append(link);
                return;
            }
            $menu = $("#main-navigation .mb2mm");
            if ($menu.length) {
                $menu.append(link);
                return;
            }
        }
    };
});