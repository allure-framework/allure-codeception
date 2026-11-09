export default {
  name: "Allure Codeception",
  output: "./out/allure-report",
  plugins: {
    testops: {
      options: {
        launchName: `Allure Codeception GitHub actions run (${new Date().toISOString()})`,
      },
    },
  },
};
