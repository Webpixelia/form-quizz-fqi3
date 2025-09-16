(function () {
  // Ensure wp.i18n is available
  if (typeof wp === 'undefined' || !wp.i18n) {
    return;
  }

  const { __ } = wp.i18n;

  function getDriverSteps() {
    const steps = [
      {
        element: '.fqi3-tab-link:nth-child(1)',
        popover: {
          title: __('License', 'form-quizz-fqi3'),
          description: __(
            "Configure your GitHub personal token here to enable access to add-ons and receive updates for the core plugin and installed add-ons.",
            'form-quizz-fqi3'
          ),
        },
      },
      {
        element: '.fqi3-tab-link:nth-child(2)',
        popover: {
          title: __('General Settings', 'form-quizz-fqi3'),
          description: __(
            "Configure the plugin's core options, such as sales page link, daily/free trial limits and question limits. Data deletion, social sharing, statistics and purge tools are available in the Tools tab.",
            'form-quizz-fqi3'
          ),
        },
      },
      {
        element: '.fqi3-tab-link:nth-child(3)',
        popover: {
          title: __('Levels Settings', 'form-quizz-fqi3'),
          description: __('Set the quiz levels: id, name and free or not', 'form-quizz-fqi3'),
        },
      },
      {
        element: '.fqi3-tab-link:nth-child(4)',
        popover: {
          title: __('Access Control', 'form-quizz-fqi3'),
          description: __(
            'This access control system allows you to define granular roles and permissions for different features and grant Premium features to administrators.',
            'form-quizz-fqi3'
          ),
        },
      },
      {
        element: '.fqi3-tab-link:nth-child(5)',
        popover: {
          title: __('Email Options', 'form-quizz-fqi3'),
          description: __(
            'Configure email notifications, disable them if necessary, upload an image for emails, set the time to send reminders, and test sending emails to the administrator.',
            'form-quizz-fqi3'
          ),
        },
      },
      {
        element: '.fqi3-tab-link:nth-child(6)',
        popover: {
          title: __('Content Options', 'form-quizz-fqi3'),
          description: __(
            'Enable RTL mode for right-to-left languages, customize the title/description before the quiz form, and set text, background, and button colors for an interface that suits your needs.',
            'form-quizz-fqi3'
          ),
        },
      },
      {
        element: '.fqi3-tab-link:nth-child(7)',
        popover: {
          title: __('Badge Management', 'form-quizz-fqi3'),
          description: __(
            'Disable badges or their legends, and manage badges related to quiz completion level and success rate, provided that statistics are enabled.',
            'form-quizz-fqi3'
          ),
        },
      },
      {
        element: '.fqi3-tab-link:nth-child(8)',
        popover: {
          title: __('API Options', 'form-quizz-fqi3'),
          description: __('Enable access to plugin data via REST API and generate an API token to secure requests.', 'form-quizz-fqi3'),
        },
      },
      {
        element: '.fqi3-tab-link:nth-child(9)',
        popover: {
          title: __('Tools', 'form-quizz-fqi3'),
          description: __(
            'Maintenance and utilities: enable automatic purge of attempts via daily cron, set the retention period (days), run a manual purge from the Attempts page, and manage data-related options (delete data on uninstall, disable social sharing and statistics).',
            'form-quizz-fqi3'
          ),
        },
      },
    ];

    // Conditionally add Questions Bank step if the add-on tab exists
    const qbSelector = '.fqi3-tab-link[data-tab="qb_assignment"]';
    const qbEl = document.querySelector(qbSelector);
    if (qbEl) {
      steps.push({
        element: qbSelector,
        popover: {
          title: __('Questions Bank', 'form-quizz-fqi3'),
          description: __(
            'Create, manage, and reuse shared questions across quizzes. This tab is provided by the Questions Bank add-on.',
            'form-quizz-fqi3'
          ),
        },
      });
    }

    return steps;
  }

  // Expose globally for admin-script.js consumption
  window.fqi3GetDriverSteps = getDriverSteps;
})();
