(function() {
  $(function() {
    var duration, remaining, tour,no_nav_template,start_template,next_template,final_template;
    duration = 5000;
    remaining = duration;
    final_template = "<div class='popover popover-double-width tour'><div class='arrow'></div><button type='button' data-role='end' class='close padminus'>&times;</button><h3 class='popover-title'></h3><div class='popover-content popover-content-large'></div><div class='popover-navigation'><button class='btn btn-default' data-role='end'>Close</button>";
    next_template = "<div class='popover popover-normal-width tour'><div class='arrow'></div><button type='button' data-role='end' class='close padminus'>&times;</button><h3 class='popover-title'></h3><div class='popover-content popover-content-large'></div><div class='popover-navigation'><button class='btn btn-default btn-success pull-right' data-role='next'>Next</button>";
    no_nav_template = "<div class='popover popover-normal-width tour'><div class='arrow'></div> <button type='button' data-role='end' class='close padminus'>&times;</button><h3 class='popover-title'></h3><div class='popover-content popover-content-large'></div>";
    start_template = "<div class='popover popover-double-width tour'><div class='arrow'></div><h3 class='popover-title'></h3><div class='popover-content popover-content-large'></div><div class='popover-navigation'><button class='btn btn-default btn-success' data-role='next'>Get started with the basics</button><button class='btn btn-default' data-role='end'>Skip this tutorial</button>";
    tour = new Tour({
      debug: true,
      onEnd: function () {
          $.ajax({
              url: '/user/welcome',
              type: 'PUT',
              success: function(response) {

              }
          });
      },
      steps: [
        {
          path: "/dashboard/status",
          orphan: true,
          placement: "bottom",
          title: "Welcome to Xibo!",
          content: "With Xibo in the Cloud we take care of managing your CMS allowing you to concentrate on your content, so let's get creating!",
          template: start_template
        }, {
          path: "/dashboard/status",
          element: ".sidebar-main:eq(0)",
          placement: "right",
          title: "",
          content: "The Dashboard menu allows for navigation to all key areas.",
          template: next_template
        }, {
          path: "/dashboard/status",
          element: ".sidebar-list:eq(3)",
          placement: "right",
          title: "",
          content: "Start by clicking on <b>Layouts</b> from your Dashboard.",
          reflex: true,
          template:no_nav_template
        }, {
          path: "/layout/view",
          orphan: true,
          placement: "top",
          title: "",
          content: "This is your Layout menu and from here is where you can access all your saved Layouts.",
          template: next_template
        }, {
          path: "/layout/view",
          element: ".btn-success a",
          placement: "left",
          title: "",
          content: "Create a new Layout by clicking on <b>Add Layout</b>",
          reflex: true,
          delay:1000,
          template:no_nav_template
        }, {
          element: "#name",
          placement: "bottom",
          title: "",
          content: "Name your Layout so you can easily identify it in the Layout menu.",
          reflex: true,
          delay:1000,
          template:no_nav_template
        }, {
          element: ".save-button",
          placement: "top",
          title: "",
          content: " Press  <b>Save</b>. ",
          reflex: true,
          template:no_nav_template
        }, {
          orphan: true,
          placement: "top",
          title: "",
          content: "This is the Layout designer screen and by default, your Layout has one empty Region added, think of it as a placeholder for your content.",
          onShown:function (tour) { console.log($(".form-error").length === 1);if($(".form-error").length === 1){tour.prev();} },
          delay:1000,
          template: next_template
        }, {
          title: "",
          content: "Grab the Region handle to click and drag to resize",
          element: ".ui-icon-gripsmall-diagonal-se:eq(0)",
          placement: "right",
          reflex: true,
          template:no_nav_template
        }, {
          title: "",
          content: "Click anywhere inside the Region click and drag to position on your Layout",
          element: ".preview:eq(0)",
          placement: "top",
          reflex: true,
          template:no_nav_template
        }, {
          title: "",
          content: "When you are happy with your positioning click on Save Region Positions. You'll need to click Save Region Positions every time you move Regions around otherwise your change will be lost!",
          element: "#layout-save-all",
          placement: "bottom",
          reflex: true,
          template:no_nav_template
        }, {
          title: "",
          content: "You now have an empty Region on a black background on your Layout, so lets add some content!",
          element: ".preview:eq(0)",
          placement: "top",
          onNext:function (tour) {$(".regionInfo").show(); $(".regionInfo .dropdown-menu").show(); },
          template: next_template
        }, {
          title: "",
          content: "Use the Region menu to select <b>Edit Timeline</b>",
          element: ".regionInfo li:eq(0) a",
          placement: "right",
          reflex: true,
          template:no_nav_template
        }, {
          title: "",
          content: "This is your Region Timeline, which is empty at the moment. The menu to the left is your Widget Toolbox and displays a variety of media types which can be added to your Region Timeline.",
          orphan: true,
          placement: "top",
          template: next_template
        }, {
          title: "",
          content: "Let's add some <b>Text</b> to the Region Timeline",
          element: "a:contains('Text')",
          placement: "right",
          reflex: true,
          template:no_nav_template
        }, {
          title: "",
          content: "This is the text editor where you can add and format your text. The thin red line is showing your Region borders so ensure that when you are editing your text remains visible within these guidelines.",
          orphan: true,
          template: next_template
        }, {
          title: "",
          content: "Enter some text, apply some formatting such as font style and size and when you are happy click <b>Save</b>",
          element: ".modal-dialog .save-button",
          placement: "top",
          reflex: true,
          template:no_nav_template
        }, {
          title: "",
          content: "Your Region Timeline will now update and show that one item has been added to the playlist, click <b>Close</b>.",
          element: "button:contains('"+translations.close+"')",
          placement: "top",
          reflex: true,
          template:no_nav_template,
          delay:1000
        }, {
          title: "",
          content: "Your Layout will now show your text assigned to this Region.",
          element: "#layout",
          placement: "right",
          template: next_template
        }, {
          title: "",
          content: "Want to know how your Layout will look on your screens?\nSwitch to the <b>Actions tab</b>",
          element: "#action-tab",
          placement: "right",
          reflex: true,
          template:no_nav_template
        }, {
          title: "",
          content: "By clicking on <b>Preview Layout</b> your Layout will open in a new window so you can see exactly how it will play!",
          element: "#tab2primary .btn-success",
          placement: "bottom",
          reflex: true,
          template:no_nav_template,
          delay:1000
        }, {
          title: "Success!",
          content: "You have mastered the basics to create a Layout! \nReady for more? Try creating a <a href='https://community.xibo.org.uk/t/how-to-create-a-simple-layout/13108'>Simple Layout</a> by following our easy step by step guide. ",
          orphan: true,
          template: final_template
        }
      ]
    });
    if(newUserWizard === 1){
       tour.init();
       tour.start();
    }
    

  });

}).call(this);

