/**
 * @textdomain form-quizz-fqi3
 */

const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;
const { createElement: el } = wp.element;
const ServerSideRender = wp.serverSideRender;
const { 
  InspectorControls, 
  BlockControls,
  useBlockProps
} = wp.blockEditor;
const { PanelBody, RangeControl, ToggleControl, TextControl } = wp.components;

const category = 'form-quizz-fqi3';

const commonSupports = {
  html: false,
  align: true,
  color: {
    text: true,
    background: true,
    gradients: true,
  },
  spacing: {
    margin: true,
    padding: true,
  },
  typography: {
    fontFamily: true,
    fontSize: true,
    fontStyle: true,
    fontWeight: true,
    lineHeight: true,
    letterSpacing: true,
    textTransform: true,
    textDecoration: true,
  },
  __experimentalBorder: {
    radius: true,
    width: true,
    color: true,
    style: true,
  },
  dimensions: {
    width: true,
    height: true,
  },
  __experimentalShadow: true,
};

// Function factory for simple blocks with preview
const createSimpleBlockEdit = (previewText, previewClass) => {
  return function () {
    const blockProps = useBlockProps();
    return el('div', blockProps, [
      el('div', { className: previewClass }, previewText)
    ]);
  };
};

// Function to create quiz-form edit (more complex)
const createQuizFormEdit = () => {
  return function(props) {
    const { attributes, setAttributes } = props;
    const { defaultLevel, showTimer, timerDuration, showSocialShare, showIncorrectAnswers, welcomeMessage } = attributes;
    
    const blockProps = useBlockProps();

    return el('div', blockProps, [
      el(InspectorControls, {},
        el(PanelBody, { title: __('Block Settings', 'form-quizz-fqi3'), initialOpen: true }, [
          el(TextControl, { 
            label: __('Welcome Message', 'form-quizz-fqi3'), 
            value: welcomeMessage, 
            onChange: (val) => setAttributes({ welcomeMessage: val }) 
          }),
          el(ToggleControl, { 
            label: __('Show Timer', 'form-quizz-fqi3'), 
            checked: showTimer, 
            onChange: (val) => setAttributes({ showTimer: val }) 
          }),
          showTimer && el(RangeControl, { 
            label: __('Timer Duration (minutes)', 'form-quizz-fqi3'), 
            value: timerDuration, 
            onChange: (val) => setAttributes({ timerDuration: val }), 
            min: 1, 
            max: 60 
          }),
          el(ToggleControl, { 
            label: __('Show Social Share Buttons', 'form-quizz-fqi3'), 
            checked: showSocialShare, 
            onChange: (val) => setAttributes({ showSocialShare: val }) 
          }),
          el(ToggleControl, { 
            label: __('Show Incorrect Answers', 'form-quizz-fqi3'), 
            checked: showIncorrectAnswers, 
            onChange: (val) => setAttributes({ showIncorrectAnswers: val }) 
          }),
        ])
      ),
      el(ServerSideRender, { 
        block: 'fqi3/quiz-form', 
        attributes: attributes 
      })
    ]);
  };
};

// Simple blocks configuration
const blocks = [
  {
    name: 'fqi3/quiz-form',
    title: __('Quiz Form', 'form-quizz-fqi3'),
    icon: 'feedback',
    description: __('Display the main quiz interface.', 'form-quizz-fqi3'),
    attributes: {
      showTimer: { type: 'boolean', default: false },
      timerDuration: { type: 'number', default: 5 },
      showSocialShare: { type: 'boolean', default: true },
      showIncorrectAnswers: { type: 'boolean', default: true },
      welcomeMessage: { type: 'string', default: __('Bienvenue au quiz', 'form-quizz-fqi3') },
    },
    edit: createQuizFormEdit(),
    save: () => null // SSR
  },
  {
    name: 'fqi3/remaining-attempts',
    title: __('Remaining Attempts', 'form-quizz-fqi3'),
    icon: 'clock',
    description: __('Show the remaining free attempts for the current user.', 'form-quizz-fqi3'),
    edit: createSimpleBlockEdit(
      __('Remaining Attempts Preview', 'form-quizz-fqi3'),
      'remaining-attempts-preview'
    ),
  },
  {
    name: 'fqi3/user-statistics',
    title: __('User Statistics', 'form-quizz-fqi3'),
    icon: 'chart-line',
    description: __('Show the user statistics.', 'form-quizz-fqi3'),
    edit: createSimpleBlockEdit(
      __('User Statistics Preview', 'form-quizz-fqi3'),
      'user-statistics-preview'
    ),
  },
  {
    name: 'fqi3/comparative-statistics',
    title: __('Comparative Statistics', 'form-quizz-fqi3'),
    icon: 'chart-pie',
    description: __('Show the comparative statistics.', 'form-quizz-fqi3'),
    edit: createSimpleBlockEdit(
      __('Comparative Statistics Preview', 'form-quizz-fqi3'),
      'comparative-statistics-preview'
    ),
  },
  {
    name: 'fqi3/current-user-badges',
    title: __('Current User Badges', 'form-quizz-fqi3'),
    icon: 'shield',
    description: __('Show the current user badges.', 'form-quizz-fqi3'),
    edit: createSimpleBlockEdit(
      __('Current User Badges Preview', 'form-quizz-fqi3'),
      'current-user-badges-preview'
    ),
  },
  {
    name: 'fqi3/periodic-stats',
    title: __('Periodic Statistics', 'form-quizz-fqi3'),
    icon: 'chart-area',
    description: __('Show the periodic statistics.', 'form-quizz-fqi3'),
    edit: createSimpleBlockEdit(
      __('Periodic Statistics Preview', 'form-quizz-fqi3'),
      'periodic-stats-preview'
    ),
  }
];

// Save blocks with common configuration
blocks.forEach(block => {
  registerBlockType(block.name, {
    apiVersion: 3,
    title: block.title,
    icon: block.icon,
    category,
    description: block.description,
    attributes: block.attributes || {},
    edit: block.edit,
    save: block.save || (() => null),
    supports: commonSupports,
  });
});

// Export utilities for add-ons
window.fqi3Blocks = window.fqi3Blocks || {};
window.fqi3Blocks.utils = {
    registerBlockType: wp.blocks.registerBlockType,
    commonSupports: commonSupports,
    createSimpleBlockEdit: createSimpleBlockEdit
};

// Optional: Dispatch an event when utils are ready
document.dispatchEvent(new Event('fqi3Blocks:utilsReady'));