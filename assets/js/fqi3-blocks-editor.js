(function () {
  const { registerBlockType } = wp.blocks;
  const { __ } = wp.i18n;
  const { createElement: el } = wp.element;
  const ServerSideRender = wp.serverSideRender;

  const category = 'form-quizz-fqi3';

  const blocks = [
    {
      name: 'fqi3/quiz-form',
      title: __('Quiz Form', 'form-quizz-fqi3'),
      icon: 'editor-help',
      description: __('Display the main quiz interface.', 'form-quizz-fqi3'),
    },
    {
      name: 'fqi3/remaining-attempts',
      title: __('Remaining Attempts', 'form-quizz-fqi3'),
      icon: 'clock',
      description: __('Show the remaining free attempts for the current user.', 'form-quizz-fqi3'),
    },
    {
      name: 'fqi3/user-statistics',
      title: __('User Statistics', 'form-quizz-fqi3'),
      icon: 'chart-bar',
      description: __('Display the current user statistics.', 'form-quizz-fqi3'),
    },
    {
      name: 'fqi3/comparative-statistics',
      title: __('Comparative Statistics', 'form-quizz-fqi3'),
      icon: 'chart-pie',
      description: __('Display comparative statistics charts.', 'form-quizz-fqi3'),
    },
    {
      name: 'fqi3/current-user-badges',
      title: __('Current User Badges', 'form-quizz-fqi3'),
      icon: 'awards',
      description: __('Show the badges earned by the current user.', 'form-quizz-fqi3'),
    },
    {
      name: 'fqi3/periodic-stats',
      title: __('Periodic Statistics', 'form-quizz-fqi3'),
      icon: 'chart-line',
      description: __('Show periodic statistics for the current user.', 'form-quizz-fqi3'),
    },
  ];

  blocks.forEach(({ name, title, icon, description }) => {
    registerBlockType(name, {
      title,
      icon,
      category,
      description,
      supports: { html: false },
      edit: function (props) {
        return el('div', { className: 'fqi3-block-editor' }, [
          el(ServerSideRender, { block: name, attributes: props.attributes }),
        ]);
      },
      save: function () {
        return null; // SSR
      },
    });
  });
})();
